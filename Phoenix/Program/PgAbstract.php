<?php

namespace Phoenix\Program;

use Phoenix\Core\Exception;
use Phoenix\Core\Reborn;

abstract class PgAbstract
{
    const TAG_PHPINI = "phpini_conf";
    const TAG_FMP    = "fpm_conf";
    CONST TAG_NGINX  = "nginx_conf";

    protected function buildConfContent($sysName, $tagName, $distFileName, $yamlConf)
    {
        $tplContent = $this->parseConf($sysName, $tagName, $yamlConf[Reborn::APP_TPL]);
        if (!$tplContent) {
            throw new Exception("app_conf 配置内容不存在！");
        }

        return Utls::buildDistConf($distFileName, $yamlConf, $tplContent);
    }


    protected function parseConf($sysName, $tagName, $confFile)
    {
        if (!file_exists($confFile)) {
            throw new Exception("{$sysName}：{$confFile}配置文件不存在！");
        }
        $content = file_get_contents($confFile);
        preg_match_all('/<' . $tagName . '>([\s\S]*)<\/' . $tagName . '>/', $content, $match);
        $confContent = $match[1][0];
        if (!$confContent) {
            throw new Exception("{$sysName}--{$tagName}：内容获取为空，请检查格式是否为<{$tagName}></{$tagName}>");
        }

        return $confContent . "\n" . $this->getTagLibrary($tagName);
    }

    private function getTagLibrary($tagName)
    {
        $library = $_SERVER[Reborn::PX_HOME] . "/Phoenix/library";
        if ($tagName == self::TAG_PHPINI) {
            return file_get_contents($library . "/php.ini");
        }

        return "";
    }

    protected static function isConf($cmd)
    {
        return $cmd == "conf";
    }

    protected function getConfDistFile($sysName, $fileType, $isCheckExist = false)
    {
        $distFile = Reborn::getConfPath() . "/{$sysName}" . $fileType;
        if ($isCheckExist && !file_exists($distFile)) {
            throw new Exception("{$sysName}：{$distFile}配置文件不存在！");
        }

        return $distFile;
    }
}