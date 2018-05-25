<?php

namespace Phoenix\Program;

use Phoenix\Core\Exception;
use Phoenix\Core\Reborn;
use Phoenix\Core\ShellExec;
use Phoenix\Extension\Daemon;

class Script extends PgAbstract
{
    const IN_DAEMON_CMD = "daemonziing";
    const FILE_PHPINI   = "_php.ini";

    const DAEMON_PHP = "daemon_php";
    const SHELL      = "shell";

    private $yamlConf;

    public function __construct($yamlConf)
    {
        $this->yamlConf = $yamlConf;
    }

    public static function run($sysName, $yamlConf, $cmd)
    {
        $phoenixSvc = new Script($yamlConf);
        $phoenixSvc->exec($cmd, $sysName);
    }

    public function exec($cmd, $sysName)
    {
        if (self::isPhp($cmd)) {
            $phpIniFile = $this->getConfDistFile($sysName, self::FILE_PHPINI, true);
            $this->execPhp($this->yamlConf[Reborn::PHP_BIN], $phpIniFile, $_SERVER["argv"], $this->yamlConf);
        } elseif (self::isShell($cmd)) {
            $this->runShell("start", [$_SERVER["argv"][2]]);
        } elseif (self::isConf($cmd)) {
            PhpClsIndex::buildClsIndex($this->yamlConf["SYS_INCLUDE"]);
            if (!file_exists($this->yamlConf[Reborn::APP_TPL])) {
                ShellExec::out("{$sysName} conf cmd nothing todo !");

                return;
            }
            $tplContent = file_get_contents($this->yamlConf[Reborn::APP_TPL]);
            if (!strpos($tplContent, self::TAG_PHPINI)) {
                ShellExec::out("{$sysName} conf cmd nothing todo !");

                return;
            }
            $distPhpIniFile = $this->getConfDistFile($sysName, self::FILE_PHPINI);
            $this->buildConfContent($sysName, self::TAG_PHPINI, $distPhpIniFile, $this->yamlConf);
        } else {
            if ($cmd == self::IN_DAEMON_CMD) {
                $this->daemonize($sysName, array_slice($_SERVER["argv"], 6));
            } else {
                $this->runDaemonPHP($sysName, $cmd, $this->yamlConf[Reborn::SYS_VARS][self::DAEMON_PHP]);
                $this->runShell($cmd, $this->yamlConf[Reborn::SYS_VARS][self::SHELL]);
            }
        }
    }

    private function runShell($cmd, $shellScript = [])
    {
        if (Daemon::isStart($cmd)) {
            if (!$shellScript) return;
            if ($this->yamlConf[Reborn::PRJ_ENV] == "online") {
                ShellExec::out("[{$this->yamlConf[Reborn::PRJ_ENV]}]环境中 shell script 不允许运行！");

                return;
            }
            foreach ($shellScript as $script) {
                $pid = pcntl_fork();
                if ($pid == 0) {
                    pcntl_exec("/bin/bash", [$script], $this->yamlConf);
                } elseif ($pid > 0) {
                    pcntl_wait($status);
                }
            }
        }

        return;
    }

    private function runDaemonPHP($sysName, $cmd, $scriptArr = [])
    {
        if (Daemon::isStart($cmd)) {
            if (!$scriptArr) return;
            $this->getConfDistFile($sysName, self::FILE_PHPINI, true);
            $this->daemonizeStop($sysName);
            if (!$scriptArr) {
                throw new Exception("{$sysName}缺少脚本配置参数：script");
            }
            foreach ($scriptArr as $script) {
                if (!$script) continue;
                $cmd = $_SERVER[Reborn::PX_BIN] . " " . self::IN_DAEMON_CMD . " -s {$sysName} -e " . $_SERVER[Reborn::PRJ_ENV] . " {$script}";
                exec($cmd);
                ShellExec::out($cmd);
            }
        } elseif (Daemon::isStop($cmd)) {
            $this->daemonizeStop($sysName);
        }

        return;
    }


    private static function isPhp($cmd)
    {
        return $cmd == "php";
    }

    private static function isShell($cmd)
    {
        return $cmd == "sh";
    }

    private function execPhp($phpBin, $phpIni, $argv, $environment)
    {
        array_shift($argv);
        array_shift($argv);
        array_unshift($argv, "-c", $phpIni);
        $count = count($argv);
        $argv  = array_slice($argv, 0, $count - 4);
        pcntl_exec($phpBin, $argv, $environment);
    }


    private function daemonize($sysName, array $daemonPhpFile)
    {
        $daemonPhpFile = implode(" ", $daemonPhpFile);
        $script        = $this->yamlConf[Reborn::SYS_VARS][self::DAEMON_PHP];
        if (!$script) {
            throw new Exception("{$sysName}缺少脚本配置参数：script");
        }
        $phpIniFile = $this->getConfDistFile($sysName, self::FILE_PHPINI, true);
        $scriptArr  = $this->filterScript($script);
        foreach ($scriptArr as $arr) {
            $phpFile   = $arr["phpFile"];
            $argsArr   = $arr["args"];
            $orgScript = $arr["scriptFile"];
            if ($orgScript == $daemonPhpFile) {
                $daemonDTO = $this->getDaemonDTO($sysName, $phpFile, $argsArr, $phpIniFile);
                \Phoenix\Extension\Daemon::exec($daemonDTO, "start");
            }
        }
    }

    private function daemonizeStop($sysName)
    {
        $daemonDTO = $this->getDaemonDTO($sysName, "", [], "");
        \Phoenix\Extension\Daemon::exec($daemonDTO, "stop");
    }

    private function filterScript($script)
    {
        $scriptArr = [];
        foreach ($script as $scriptFile) {
            $scriptFile    = trim($scriptFile);
            $tmpArr        = explode(" ", $scriptFile);
            $tmpArr        = array_filter($tmpArr, function ($v) {
                return $v !== "";
            });
            $orgScriptFile = implode(" ", $tmpArr);
            $scriptFile    = array_shift($tmpArr);
            $scriptArr[]   = ["phpFile" => $scriptFile, "args" => $tmpArr, "scriptFile" => $orgScriptFile];
            if (!file_exists($scriptFile)) {
                throw new Exception("{$scriptFile}脚本文件不存在！");
            }
        }

        return $scriptArr;
    }


    private function getDaemonDTO($sysName, $script, $argsArr = [], $phpIni = '')
    {
        $daemon              = new \Phoenix\DTO\Daemon();
        $daemon->runPath     = $this->yamlConf[Reborn::RUN_PATH] . "/{$sysName}";
        $daemon->phpBin      = $this->yamlConf[Reborn::PHP_BIN];
        $daemon->environment = $this->yamlConf;
        $user                = $this->yamlConf[Reborn::PRJ_ENV] == "dev" ? $this->yamlConf[Reborn::RUN_USER] : "root";
        $daemon->user        = $user;
        $daemon->phpArgs     = $argsArr;
        $daemon->phpFile     = $script;
        $daemon->phpIni      = $phpIni;

        return $daemon;
    }


}