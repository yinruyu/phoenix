<?php

namespace Phoenix\Core;

use Phoenix\Program\Crontab;
use Phoenix\Program\NginxWeb;
use Phoenix\Program\PubLibrary;
use Phoenix\Program\Release;
use Phoenix\Program\Script;
use Phoenix\Program\Web;

class Reborn
{
    const RUN_PATH     = "RUN_PATH";
    const FPM_PID      = "FPM_PID";
    const SYS_DOMAIN   = "SYS_DOMAIN";
    const FPM_ENV_VARS = "FPM_ENV_VARS";
    const FPM_ENV      = "FPM_ENV";
    const SYS_VARS     = "SYS_VARS";
    const PHP_BIN      = "PHP_BIN";
    const FPM_BIN      = "FPM_BIN";
    const RUN_USER     = "RUN_USER";
    const PX_BIN       = "PX_BIN";
    const APP_TPL      = "APP_TPL";
    const PRJ_ENV      = "PRJ_ENV";
    const LOG_PATH     = "LOG_PATH";
    const PX_HOME      = "PX_HOME";

    private static $confPath;

    public function __construct($yamlConf)
    {
        $this->yamlConf = $yamlConf;
    }

    public static function run($yamlFile, $argv)
    {
        $cmdSvc  = new Command($argv);
        $cmdArrs = $cmdSvc->getCmd();
        foreach ($cmdArrs as $cmd) {
            #运行命令
            if (Release::isRc($cmd)) {
                $yamlConf = Yaml::getYamlConfByRoot($yamlFile);
                Release::run($yamlConf, $cmd);
                break;
            }
            #运行系统
            $env     = $cmdSvc->getEnv();
            $sysArrs = $cmdSvc->getSystem();
            foreach ($sysArrs as $sysName) {
                $yamlConf = Yaml::getYamlConfBySysName($yamlFile, $env, $sysName);
                $cmd      = strtolower($cmd);
                self::execSys($sysName, $yamlConf, $cmd);
                self::runScript($sysName, $yamlConf, $cmd);
            }
        }
    }

    public static function getConfPath()
    {
        return self::$confPath;
    }

    private static function execSys($sysName, $yamlConf, $cmd)
    {
        self::initRunPathSys($yamlConf[self::RUN_PATH], $sysName);
        if (!file_exists($yamlConf[self::LOG_PATH])) {
            self::chmodDir($yamlConf[self::LOG_PATH], $yamlConf[self::RUN_USER]);
        }
        $moudle = $yamlConf[self::SYS_VARS]["module"];
        switch ($moudle) {
            case "web":
                self::runWeb($sysName, $yamlConf, $cmd);
                break;
            case "script" :
                break;
            case "nginxWeb" :
                self::runNginxWeb($sysName, $yamlConf, $cmd);
                break;
            case "crontab" :
                self::runCrontab($sysName, $yamlConf, $cmd);
                break;
            case "pubLib" :
                self::runPubLib($sysName, $yamlConf, $cmd);
                break;
            default:
                throw new Exception("{$sysName}缺少module配置类型！");
        }
    }

    private static function runScript($sysName, $yamlConf, $cmd)
    {
        Script::run($sysName, $yamlConf, $cmd);
    }

    private static function runWeb($sysName, $yamlConf, $cmd)
    {
        Web::run($sysName, $yamlConf, $cmd);
    }

    private static function runCrontab($sysName, $yamlConf, $cmd)
    {
        Crontab::run($sysName, $yamlConf, $cmd);
    }

    private static function runPubLib($sysName, $yamlConf, $cmd)
    {
        PubLibrary::run($sysName, $yamlConf, $cmd);
    }

    private static function runNginxWeb($sysName, $yamlConf, $cmd)
    {
        NginxWeb::run($sysName, $yamlConf, $cmd);
    }

    private static function initRunPathSys($runPath, $sysName)
    {
        self::initRunPath($runPath);
        $confPath       = $runPath . "/{$sysName}/conf";
        self::$confPath = $confPath;
        if (!file_exists($confPath)) {
            $cmd = "sudo mkdir -p {$confPath}";
            ShellExec::exec($cmd);
        }
    }

    public static function chmodDir($dir, $user)
    {
        if (!$dir) {
            throw new Exception("{$dir}不能为空！");
        }
        $cmdArr = [];
        if (!file_exists($dir)) {
            $cmdArr[] = "sudo mkdir -p {$dir}";
        }
        $cmdArr[] = "sudo sh -c 'chown {$user}:{$user} {$dir}'";
        ShellExec::exec(implode(" && ", $cmdArr));
    }

    public static function initRunPath($runPath)
    {
        if (!$runPath) {
            throw new Exception("RUN_PATH 参数不能为空！");
        }
        if (!file_exists($runPath)) {
            $cmd = "sudo mkdir -p {$runPath}";
            ShellExec::exec($cmd, false);
        }
        if (!defined("PRJ_RUN_PATH")) {
            define("PRJ_RUN_PATH", $runPath);
        }
    }
}