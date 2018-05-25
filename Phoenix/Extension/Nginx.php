<?php

namespace Phoenix\Extension;

use Phoenix\Core\Exception;
use Phoenix\Core\ShellExec;

class Nginx
{
    const NGIXBIN   = '/usr/local/Cellar/nginx/1.13.12/bin/nginx';
//    const NGIXBIN   = '/usr/local/nginx/sbin/nginx';
    const VHOSTPATH = '/usr/local/nginx/conf/include/';
//    const VHOSTPATH = '/usr/local/nginx/conf/include/';

    public static function exec($cmd)
    {
        if (!self::isSupportCmd($cmd)) {
            ShellExec::out('nginx nothing to do !');

            return;
        }
        $args = func_get_args();
        list($cmd, $nginxConfFile, $domain) = $args;
        if (!$domain) {
            ShellExec::out('nginx:domin is empty, nginx nothing todo !');

            return;
        }
        $linkDst = self::getDstLink("{$domain}.conf");
        if ($cmd == 'start') {
            self::start($nginxConfFile, $linkDst);
        } elseif ($cmd == 'stop') {
            self::stop($linkDst);
        }
        ShellExec::out("nginx done [$cmd]!");
    }

    public static function start($sourceConf, $linkDst)
    {
        self::link($sourceConf, $linkDst);
    }

    public static function stop($linkDst)
    {
        $reloadCmd = self::getReloadCmd();
        $cmd       = "sudo sh -c ' if test -e  $linkDst; then  rm -f $linkDst ; $reloadCmd;  fi ' ";
        ShellExec::exec($cmd);
    }

    public static function isStart($cmd)
    {
        return in_array($cmd, ['start']);
    }

    private static function link($source, $dstLink)
    {
        $cmd = "sudo sh -c ' if test -e  $dstLink; then  rm -f $dstLink ; fi ' ";
        ShellExec::exec($cmd);

        $cmd = "sudo sh -c 'ln -s $source $dstLink ' ";
        ShellExec::exec($cmd);

        $reloadCmd = self::getReloadCmd();

        $cmd = "sudo sh -c '$reloadCmd;'";
        ShellExec::exec($cmd);
    }

    private static function getDstLink($linkName)
    {
        return self::VHOSTPATH . $linkName;
    }

    private static function getSupportCmd()
    {
        return ['start', 'stop'];
    }

    private static function isSupportCmd($cmd)
    {
        $cmds = self::getSupportCmd();

        return in_array($cmd, $cmds);
    }

    private static function getReloadCmd()
    {
        return self::NGIXBIN . " -t; " . self::NGIXBIN . " -s reload  ";
    }
}