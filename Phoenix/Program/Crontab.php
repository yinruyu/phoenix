<?php

namespace Phoenix\Program;

use Phoenix\Core\Reborn;
use Phoenix\Core\ShellExec;

class Crontab extends PgAbstract
{
    const CRONTAB_CONF = "_cron.conf";
    const TAG_CRONTAB  = "crontab";
    private $yamlConf;

    public function __construct($yamlConf)
    {
        $this->yamlConf = $yamlConf;
    }

    public static function run($sysName, $yamlConf, $cmd)
    {
        $phoenixSvc = new Crontab($yamlConf);
        $phoenixSvc->exec($cmd, $sysName);
    }

    public function exec($cmd, $sysName)
    {
        if (in_array($cmd, ["start", "stop"])) {
            $this->$cmd($sysName);
        } else {
            ShellExec::out("crontab ignore {$cmd}");
        }
    }


    public function start($sysName)
    {
        $this->stop($sysName);
        $distCrontabFile = $this->getConfDistFile($sysName, self::CRONTAB_CONF);
        $tplContent      = $this->buildConfContent($sysName, self::TAG_CRONTAB, $distCrontabFile, $this->yamlConf);
        list($flagBegin, $flagEnd, $date) = $this->getCronFlag($sysName);
        $tplContent = $flagBegin . $date . $tplContent . "\n" . $flagEnd;
        $osCrontab  = $this->getOSCrontab();
        $osCrontab  = $osCrontab . "\n" . $tplContent;
        $this->updateCrontab($osCrontab);
        ShellExec::out("{$sysName} executed  [start]");
    }


    public function stop($sysName)
    {
        $crontabContent = $this->getOSCrontab();
        list($flagBegin, $flagEnd, $date) = $this->getCronFlag($sysName);
        $flagBegin = str_replace("\n", "", $flagBegin);
        $flagEnd   = str_replace("\n", "", $flagEnd);
        $reg       = "/({$flagBegin})([\s\S]{0,})({$flagEnd})/";
        preg_match_all($reg, $crontabContent, $match);
        if ($match[0][0]) {
            $crontabContent = preg_replace($reg, "", $crontabContent);
            $crontabContent = explode("\n", $crontabContent);
            $crontabContent = array_filter($crontabContent, function ($v) {
                return $v != "";
            });
            $crontabContent = implode("\n", $crontabContent);
            $this->updateCrontab($crontabContent);
            ShellExec::out("{$sysName} executed  [stop]");
        } else {
            ShellExec::out("{$sysName} nothing to do !");
        }
    }

    private function updateCrontab($crontabContent)
    {
        $crontabUser = "root";
        if ($_SERVER[Reborn::PRJ_ENV] == "dev") {
            $crontabUser = $this->yamlConf[Reborn::RUN_USER];
        }
        ShellExec::write("/var/spool/cron/{$crontabUser}", $crontabContent);
    }

    private function getOSCrontab()
    {
        $crontabArrs = ShellExec::exec('crontab -l');

        return implode("\n", $crontabArrs);
    }

    private function getCronFlag($sysName)
    {
        $powerBy         = "POWER BY PHOENIX-NG---";
        $distCrontabFile = $this->getConfDistFile($sysName, self::CRONTAB_CONF);
        $key             = str_replace("/", "-", $distCrontabFile);
        $begin           = "# {$key}\t{$powerBy}-BEGIN\n";
        $end             = "# {$key}\t{$powerBy}-END\n";
        $date            = "# Date " . date("Y-m-d H:i:s") . "\n";

        return [$begin, $end, $date];
    }


}