<?php

namespace Phoenix\Program;

use Phoenix\Core\Exception;
use Phoenix\Extension\Git;

class Release extends PgAbstract
{
    private $yamlConf;

    public function __construct($yamlConf)
    {
        $this->yamlConf = $yamlConf;
    }

    public static function run($yamlConf, $cmd)
    {
        $release = new Release($yamlConf);
        $release->rc();
    }

    public static function isRc($cmd)
    {
        return $cmd == "rc";
    }

    public function rc()
    {
        Git::status();
        $versionArr = $this->getVersion();
        fwrite(STDOUT, $this->startMsg());
        $type = strtolower(trim(fgets(STDIN)));
        switch ($type) {
            case "w":
                $newVer = $this->workingAdd($versionArr);
                break;
            case "b":
                $newVer = $this->fixbugAdd($versionArr);
                break;
            case "f":
                $newVer = $this->featureAdd($versionArr);
                break;
            case "s":
                $newVer = $this->revolution($versionArr);
                break;
            default:
                throw new Exception("发布版本失败：{$type}类型不支持！");
        }

        echo $this->endMsg($versionArr, $newVer);
        Git::tag(implode(".", $newVer));
    }

    private function workingAdd(array $versionArr)
    {
        $versionArr[3] += 1;

        return $this->updateVersion($versionArr);
    }

    private function fixbugAdd(array $versionArr)
    {
        $versionArr[2] += 1;

        return $this->updateVersion($versionArr);
    }

    private function featureAdd(array $versionArr)
    {
        $versionArr[1] += 1;

        return $this->updateVersion($versionArr);
    }

    private function revolution(array $versionArr)
    {
        $versionArr[0] += 1;

        return $this->updateVersion($versionArr);
    }

    private function updateVersion(array $versionArr)
    {

        file_put_contents($this->getVerFile(), implode(".", $versionArr));

        return $versionArr;
    }

    private function startMsg()
    {
        return "struct revolution(s) ,add feature(f) ,fixbug(b), working(w)\n";
    }

    private function endMsg(array $oldVer, array $newVer)
    {
        return "version updated : [" . implode(".", $oldVer) . "] ---> [" . implode(".", $newVer) . "]\n";
    }

    private function getVersion()
    {
        $version = file_get_contents($this->getVerFile());
        if (!preg_match('/^[\d]{1,}\.[\d]{1,}\.[\d]{1,}\.[\d]{1,}$/', $version, $match)) {
            throw new Exception("发布版本失败：{$version}格式错误，示例：0.0.0.0");
        }

        return explode(".", $match[0]);
    }


    private function getVerFile()
    {
        $verFile = $this->yamlConf["PRJ_VERSION"];
        if (!file_exists($verFile)) {
            throw new Exception("发布版本失败：{$verFile}文件不存在！");
        }

        return $verFile;
    }
}