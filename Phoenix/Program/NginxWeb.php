<?php

namespace Phoenix\Program;

use Phoenix\Core\Exception;
use Phoenix\Core\Reborn;
use Phoenix\Extension\Nginx;

class NginxWeb extends PgAbstract
{

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
        $phoenixSvc = new self($yamlConf);
        $phoenixSvc->exec($cmd, $sysName);
    }

    public function exec($cmd, $sysName)
    {
        $ngxConfFile = $this->getConfDistFile($sysName, self::FILE_NGXCONF);
        if (self::isConf($cmd)) {
            $this->buildConfContent($sysName, self::TAG_NGINX, $ngxConfFile, $this->yamlConf);
        } else {
            $this->nginxExec($cmd, $ngxConfFile);
        }
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