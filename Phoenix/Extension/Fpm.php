<?php

namespace Phoenix\Extension;

use Phoenix\Core\ShellExec;

class Fpm
{

    private static $fpmBin;

    public static function exec($cmd, $pidFile)
    {
        if (!self::isSupportCmd($cmd)) {
            ShellExec::out('fpm nothing to do !');

            return;
        }
        $args = func_get_args();
        list($cmd, $pidFile, $iniFile, $confFile, $fpmBin, $fpmArgStr) = $args;
        self::$fpmBin = $fpmBin;
        if ($cmd == 'start') {
            self::start($pidFile, $iniFile, $confFile, $fpmArgStr);
        } elseif ($cmd == 'stop') {
            self::stop($pidFile);
        } elseif ($cmd == 'reload') {
            self::reload($pidFile);
        }
        ShellExec::out("fpm done [$cmd]!");
    }

    public static function start($PID, $INI, $CONF, $args = '')
    {
        self::stop($PID);
        $cmd = 'sudo ' . self::$fpmBin . " --pid $PID -c $INI --fpm-config $CONF $args \n";
        ShellExec::exec($cmd);
    }

    public static function stop($PIDFILE)
    {
        $cmd = "sudo sh -c ' if test -e  $PIDFILE; then  kill -QUIT `cat $PIDFILE` ; fi '  ";
        ShellExec::exec($cmd);
    }

    public function reload($PID)
    {
        $cmd = "sudo sh -c 'if test -e $PID ; then  kill -USR2  `cat $PID` ; fi'  ";
        ShellExec::exec($cmd);
    }

    public static function isStart($cmd)
    {
        return in_array($cmd, ['start', 'reload']);
    }

    public static function confEnv($fpmEnvPath, array $fpmEnvVars)
    {
        $fpmEnvString = '';
        foreach ($fpmEnvVars as $k => $v) {
            $fpmEnvString .= "env[{$k}] = {$v}\n";
        }
        ShellExec::write($fpmEnvPath, $fpmEnvString);
    }

    private static function getSupportCmd()
    {
        return ['start', 'stop', 'reload'];
    }

    private static function isSupportCmd($cmd)
    {
        $cmds = self::getSupportCmd();

        return in_array($cmd, $cmds);
    }
}