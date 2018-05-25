<?php

namespace Phoenix\Program;

use Phoenix\Core\Exception;
use Phoenix\Core\ShellExec;
use Phoenix\Core\Yaml;

class Utls
{
    public static function buildDistConf($distFileName, $yamlConf, $tplContent)
    {
        if (!$tplContent) {
            throw new Exception("app_conf 配置内容不存在！");
        }
        Yaml::parseSysvars($yamlConf, $tplContent);
        ShellExec::write($distFileName, $tplContent);

        return $tplContent;
    }

    public static function chmodFile($fileName, $user)
    {
        $cmdArr = [];
        if (!file_exists($fileName)) {
            $cmdArr[] = "sudo sh -c 'touch {$fileName}'";
        }
        $fileUid = fileowner($fileName);
        $row     = posix_getpwuid($fileUid);
        if ($row["name"] != $user) {
            $cmdArr[] = "sudo sh -c 'chown {$user}:{$user} {$fileName}'";
        }
        if ($cmdArr) {
            ShellExec::exec(implode(" && ", $cmdArr));
        }
    }
}