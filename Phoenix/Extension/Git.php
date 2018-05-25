<?php

namespace Phoenix\Extension;

use Phoenix\Core\Exception;
use Phoenix\Core\ShellExec;

class Git
{


    public static function status()
    {
        $cmdArr[] = "git status";
        $outPut   = ShellExec::exec(implode(" && ", $cmdArr));
        $checkStr = implode("\t", $outPut);
        if (strripos($checkStr, "git add")) {
            throw new Exception(implode("\n", $outPut));
        }
    }

    public static function tag($version)
    {
        $cmdArr[] = "git add src/version.txt";
        $cmdArr[] = "git commit -m'{$version}'";
        $cmdArr[] = "git tag {$version}";
        $cmdArr[] = "git push";
        $cmdArr[] = "git push --tags";

        $outPut = ShellExec::exec(implode(" && ", $cmdArr));
        echo implode("\n", $outPut) . "\n";
    }
}