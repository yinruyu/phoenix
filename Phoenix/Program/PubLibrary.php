<?php

namespace Phoenix\Program;

class PubLibrary extends PgAbstract
{

    private $yamlConf;

    public function __construct($yamlConf)
    {
        $this->yamlConf = $yamlConf;
    }

    public function exec($cmd, $sysName)
    {
        if (self::isConf($cmd)) {
            PhpClsIndex::buildClsIndex($this->yamlConf["SYS_INCLUDE"]);
        }
    }

    public static function run($sysName, $yamlConf, $cmd)
    {
        $phoenixSvc = new PubLibrary($yamlConf);
        $phoenixSvc->exec($cmd, $sysName);
    }

}