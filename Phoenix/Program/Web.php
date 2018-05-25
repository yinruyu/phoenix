<?php

namespace Phoenix\Program;

use Phoenix\Core\Exception;
use Phoenix\Core\Reborn;
use Phoenix\Extension\Fpm;
use Phoenix\Extension\Nginx;

class Web extends PgAbstract
{

    const FILE_PHPINI  = "_php.ini";
    const FILE_FPMCONF = "_fpm.conf";
    const FILE_NGXCONF = "_nginx.conf";

    private $yamlConf;
    private $confPath = null;

    public function __construct($yamlConf)
    {
        $this->yamlConf = $yamlConf;
        $this->confPath = $yamlConf[Reborn::RUN_PATH];
    }

    public static function run($sysName, $yamlConf, $cmd)
    {
        $phoenixSvc = new Web($yamlConf);
        $phoenixSvc->exec($cmd, $sysName);
    }

    public function exec($cmd, $sysName)
    {
        $iniFile     = $this->getConfDistFile($sysName, self::FILE_PHPINI);
        $fpmConfFile = $this->getConfDistFile($sysName, self::FILE_FPMCONF);
        $ngxConfFile = $this->getConfDistFile($sysName, self::FILE_NGXCONF);
        if (self::isConf($cmd)) {
            PhpClsIndex::buildClsIndex($this->yamlConf["SYS_INCLUDE"]);
            $this->buildConfContent($sysName, self::TAG_PHPINI, $iniFile, $this->yamlConf);
            $this->buildConfContent($sysName, self::TAG_FMP, $fpmConfFile, $this->yamlConf);
            $this->buildConfContent($sysName, self::TAG_NGINX, $ngxConfFile, $this->yamlConf);
            Fpm::confEnv($this->yamlConf[Reborn::FPM_ENV], $this->yamlConf[Reborn::FPM_ENV_VARS]);
        } else {
            $this->fpmExec($cmd, $iniFile, $fpmConfFile);
            $this->nginxExec($cmd, $ngxConfFile);
        }
    }

    private function fpmExec($cmd, $iniFile, $fpmConfFile)
    {
        if (Fpm::isStart($cmd)) {
            if (!file_exists($iniFile)) {
                throw new Exception("php_ini：{$iniFile}配置文件不存在！");
            }
            if (!file_exists($fpmConfFile)) {
                throw new Exception("php_fpm：{$fpmConfFile}配置文件不存在！");
            }
        }
        Fpm::exec($cmd, $this->yamlConf[Reborn::FPM_PID], $iniFile, $fpmConfFile, $this->yamlConf[Reborn::FPM_BIN], '');
    }

    private function nginxExec($cmd, $nginxConfFile)
    {
        if (Nginx::isStart($cmd)) {
            if (!file_exists($nginxConfFile)) {
                throw new Exception("nginx：{$nginxConfFile}配置文件不存在！");
            }
        }
        Nginx::exec($cmd, $nginxConfFile, $this->yamlConf[Reborn::SYS_DOMAIN]);
    }
}