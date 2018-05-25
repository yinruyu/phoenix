<?php

namespace Phoenix\Core;

class Command
{
    private $cmdStr = null;
    private $exeCmd = null;

    public function __construct(array $argvs)
    {
        $this->checkPrjEnv();
        $this->exeCmd = $argvs[1];
        if (strpos($this->exeCmd, "-") === 0) {
            throw new Exception("运行失败：缺少启动运行命令（start|stop）！");
        }
        $this->cmdStr = implode(" ", $argvs);
    }

    public function getCmd()
    {
        $cmdArrs = explode(",", $this->exeCmd);
        if (!$cmdArrs) {
            throw new Exception("运行失败：缺少启动运行命令（start|stop）！");
        }

        return $cmdArrs;
    }

    public function getEnv()
    {
        preg_match_all('/-e\s([a-zA-Z_,]*)/', $this->cmdStr, $match);
        $env = $match[1][0];
        if (!$env) {
            throw new Exception("运行失败：缺少参数 -e （环境）参数！");
        }
        $systemENV = $_SERVER[Reborn::PRJ_ENV];
        if ($systemENV != $env) {
            throw new Exception("运行失败：当前系统环境为{$systemENV}，禁止运行{$env}环境！");
        }

        return $env;
    }

    public function getSystem()
    {
        preg_match_all('/-s\s([a-zA-Z_0-9,\s]*)/', $this->cmdStr, $match);
        $sysArr = explode(",", $match[1][0]);
        foreach ($sysArr as $k => &$v) {
            $v = trim($v);
            if (!$v) unset($sysArr[$k]);
        }
        if (!$sysArr) {
            throw new Exception("运行失败：缺少 -s 参数（系统名称）！");
        }

        return $sysArr;
    }

    private function checkPrjEnv()
    {
        $systemENV = $_SERVER[Reborn::PRJ_ENV];
        if (!$systemENV) {
            throw new Exception("运行失败：缺少环境变量配置 “export PRJ_ENV=[dev|demo|online]”！");
        }
    }
}