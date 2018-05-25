<?php

namespace Phoenix\Core;

class Yaml
{

    private function getYamlVars($yamlFile)
    {

        $yamlVars   = yaml_parse_file($yamlFile);
        $yamlPxFile = \PhoenixBin::getRootSrc($_SERVER['PHOENIX_ENV']) . "/Phoenix/yaml/run.yaml";

        return $this->merge($yamlVars, yaml_parse_file($yamlPxFile));
    }

    private function getSysVars($yamVars, $sysName)
    {
        $vars = [];
        foreach ($yamVars as $v) {
            if ($v['name'] == $sysName) {
                $vars = $v;
                break;
            }
        }
        if (!$vars) {
            throw new Exception("{$sysName}系统不存在！");
        }

        return $vars;
    }

    public static function getYamlConfByRoot($yamlFile)
    {
        $yamlSvc    = new Yaml();
        $yamlVars   = $yamlSvc->getYamlVars($yamlFile);
        $envInclude = ['_base', "_" . $_SERVER[Reborn::PRJ_ENV]];
        $envExclude = ['_system'];
        list($varsKnown, $varsUnknown, $envVars) = $yamlSvc->getVarsFromYaml($yamlVars, $envInclude, $envExclude);
        $yamlSvc->parseUnknowVars($varsKnown, $varsUnknown, false);
        self::parseSysVars($varsKnown, $varsUnknown);
        Reborn::initRunPath($varsUnknown[Reborn::RUN_PATH]);

        return $varsKnown + $varsUnknown;
    }

    public static function getYamlConfBySysName($yamlFile, $env, $sysName)
    {
        $yamlSvc             = new Yaml();
        $yamlVars            = $yamlSvc->getYamlVars($yamlFile);
        $envInclude          = ['_base', '_' . $env];
        $envExclude          = ['_system'];
        $_SERVER['SYS_NAME'] = $sysName;
        list($varsKnown, $varsUnknown, $envVars) = $yamlSvc->getVarsFromYaml($yamlVars, $envInclude, $envExclude);
        $yamlSvc->parseUnknowVars($varsKnown, $varsUnknown);

        $varsSystem = $yamlSvc->getSysVars($yamlVars['_system'], $sysName);
        self::parseSysVars($varsKnown, $varsSystem);
        if (isset($varsSystem['res'])) {
            $varsKnown = $varsSystem['res'] + $varsKnown;
        }
        $envVars = $yamlSvc->setEnvVars($envVars, $varsSystem);
        $envVars = $yamlSvc->transEnvVars($envVars, $varsKnown);

        $varsKnown[Reborn::FPM_ENV_VARS] = $envVars;
        $varsKnown[Reborn::SYS_VARS]     = $varsSystem;
        Reborn::initRunPath($varsKnown[Reborn::RUN_PATH]);

        return $varsKnown;
    }


    private function getVarsFromYaml($yamlVars, $envInclude, $envExclude)
    {
        $varsKnown   = [];
        $varsUnknown = [];
        $envVars     = [];
        foreach ($_SERVER as $k => $v) {
            $varsKnown[$k] = $v;
        }
        foreach ($yamlVars as $mod => $v) {
            if (in_array($mod, $envExclude)) {
                continue;
            }
            foreach ($envInclude as $env) {
                if (isset($v[$env])) {
                    foreach ($v[$env] as $envKey => $enVal) {
                        if (!is_array($enVal)) {
                            preg_match_all('/{\$(.*?)}/', $enVal, $match);
                            if (!$match[0]) {
                                $varsKnown[$envKey] = $enVal;
                            } else {
                                $varsUnknown[$envKey] = $enVal;
                            }
                            $envVars[$envKey] = $enVal;
                        }
                    }
                }
            }
        }
        $envVars["PRJ_ENV"] = $varsKnown["PRJ_ENV"];

        return [$varsKnown, $varsUnknown, $envVars];
    }

    public static function parseSysVars($varsKnown, &$sysVars)
    {
        if (!is_array($sysVars)) {
            preg_match_all('/{\$(.*?)}/', $sysVars, $match);
            if ($match[0]) {
                foreach ($match[0] as $kn => $var) {
                    $filed = $match[1][$kn];
                    if (isset($varsKnown[$filed])) {
                        $sysVars = str_replace($var, $varsKnown[$filed], $sysVars);
                    }
                }
            }

            return $sysVars;
        } else {
            foreach ($sysVars as $k => &$v) {
                self::parseSysVars($varsKnown, $v);
            }
        }

        return null;
    }

    public function parseUnknowVars(&$varsKnown, $varsUnknown, $recursive = true)
    {
        $isRecve = false;
        foreach ($varsUnknown as $k => $v) {
            preg_match_all('/{\$(.*?)}/', $v, $match);
            if ($match[0]) {
                foreach ($match[0] as $kn => $var) {
                    $filed = $match[1][$kn];
                    if (isset($varsKnown[$filed])) {
                        $v = str_replace($var, $varsKnown[$filed], $v);
                    } else {
                        $isRecve = true;
                    }
                }
                preg_match_all('/{\$(.*?)}/', $v, $matchCheck);
                if (!$matchCheck[0]) {
                    $varsKnown[$k] = $v;
                }
                $varsUnknown[$k] = $v;
            } else {
                $varsKnown[$k] = $v;
            }
        }
        if ($isRecve && $recursive) {
            $varsUnknown = $this->parseUnknowVars($varsKnown, $varsUnknown, true);
        }

        return $varsUnknown;
    }

    private function merge($prjYamlVars, $pxYamlVars)
    {
        foreach ($pxYamlVars as $k => $v) {
            if (!isset($prjYamlVars[$k])) {
                $prjYamlVars[$k] = $v;
            } else {
                foreach ($prjYamlVars[$k] as $pk => &$pv) {
                    $pvFstK = reset(array_keys($pv));
                    if (isset($pxYamlVars[$k][$pk]) && $pvFstK !== 0) {
                        $pv = $pv + $pxYamlVars[$k][$pk];
                    }
                }
                $prjYamlVars[$k] = $prjYamlVars[$k] + $pxYamlVars[$k];
            }
        }

        return $prjYamlVars;
    }

    public function setEnvVars($envVars, $sysVars)
    {
        if(!isset($sysVars['res'])){
            return $envVars;
        }
        return $sysVars['res'] + $envVars;
    }

    public function transEnvVars($envVars, $varsKnow)
    {
        foreach ($envVars as $k => &$v) {
            if (isset($varsKnow[$k])) {
                $v = $varsKnow[$k];
            }
        }

        return $envVars;
    }
}