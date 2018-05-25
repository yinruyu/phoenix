<?php

namespace Phoenix\Core;

use Phoenix\Program\Utls;

class ShellExec
{
    public static function exec($cmd,$isLog=true)
    {
        exec($cmd, $outPut, $returnVar);
        if($isLog){
            self::execLog($cmd);
        }

        return $outPut;
    }

    public static function out($message)
    {
        echo $message . "\n";
        self::execLog($message . "\n");
    }

    public static function write($fileName, $content)
    {
        $content = str_replace('\'', '"', $content);
        $content = str_replace('$', '\$', $content);
        $content = "EOF\n" . $content . "\nEOF";
        $cmd     = "sudo sh -c 'cat > $fileName <<$content'";
        self::exec($cmd);
    }

    private static function execLog($content)
    {
        if (!defined("PRJ_RUN_PATH")) {
            throw new Exception("操作失败：PRJ_RUN_PATH 常量没有被定义!");
        }
        $logFile = PRJ_RUN_PATH . "/px_run.log";
        if (!file_exists($logFile)) {
            Utls::chmodFile($logFile, $_SERVER["USER"]);
        }
        $logHandle = fopen($logFile, "a");
        if ($logHandle) {
            fwrite($logHandle, date("Y-m-d H:i:s") . "\t{$content}\n");
            fclose($logHandle);
        }
        #echo $content . "\n";
    }
}
