<?php

namespace Phoenix\Program;

use Phoenix\Core\Exception;
use Phoenix\Core\Reborn;

class PhpClsIndex extends PgAbstract
{

    private $clsArr     = [];
    private $clsUriRule = [];

    public static function buildClsIndex($clsInclude)
    {
        if (!$clsInclude) {
            return;
        }
        $clsDirArr = explode(":", $clsInclude);
        $clsSvc    = new PhpClsIndex();
        foreach ($clsDirArr as $dir) {
            list($clsArr, $clsUriArr) = $clsSvc->scanDir($dir);
        }
        if ($clsArr) {
            self::rcDistConf($clsArr, "class.idx");
        }
        if ($clsUriArr) {
            self::rcDistConf($clsUriArr, "router.idx");
        }
    }

    private static function rcDistConf($clsArr, $fileName)
    {
        ksort($clsArr);
        $confPath = Reborn::getConfPath();
        $distFile = $confPath . "/{$fileName}";
        Utls::chmodFile($distFile, $_SERVER["USER"]);
        file_put_contents($distFile, "");
        $handle = fopen($distFile, "a");
        foreach ($clsArr as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $file) {
                    fwrite($handle, $k . ":" . $file . "\n");
                }
            } else {
                fwrite($handle, $k . ":" . $v . "\n");
            }
        }
        fclose($handle);
        $distFile = $confPath . "/{$fileName}.serialize";
        Utls::chmodFile($distFile, $_SERVER["USER"]);
        file_put_contents($distFile, serialize($clsArr));
    }


    public function scanDir($dirPath)
    {
        $current_dir = opendir($dirPath);
        if (!$current_dir) {
            throw new Exception("{$dirPath} 目录不存在！");
        }
        while (($file = readdir($current_dir)) !== false) {
            $sub_dir = $dirPath . DIRECTORY_SEPARATOR . $file;
            if ($file == '.' || $file == '..') {
                continue;
            } else {
                if (is_dir($sub_dir)) {
                    $this->scanDir($sub_dir);
                } else {
                    $fileExtension = substr(strrchr($sub_dir, '.'), 1);
                    if ('php' == strtolower($fileExtension)) {
                        $this->appendClass($this->getFileCLS($sub_dir));
                    }
                }
            }
        }

        return [$this->clsArr, $this->clsUriRule];
    }

    private function appendClass($clsArr)
    {
        $this->clsArr = array_merge_recursive($this->clsArr, $clsArr);
    }

    private function appendUriRUle($clsName, $uriRule)
    {
        $this->clsUriRule[$uriRule] = $clsName;
    }

    private function getFileCLS($fileName)
    {
        $clsArr    = [];
        $handle    = @fopen($fileName, "r");
        $namespace = '';
        $i         = 1;
        while (!feof($handle)) {
            $buffer = fgets($handle, 102400);
            preg_match_all('/\/\/@REST_RULE:\s{0,}((\/[a-zA-Z0-9_]{1,}){1,})(\/\$method)?/', $buffer, $uriMatch);//获取路由规则
            $buffer = addslashes($buffer);
            preg_match_all("/^\s*(trait|class|interface|abstract\s{1,}class|namespace)\s{1,}(([a-zA-Z_0-9]*)(\\\\[a-zA-Z_0-9]*)*){?/i",
                $buffer,
                $match);

            $clsType = isset($match[1][0]) ? strtolower($match[1][0]) : null;
            if (!$clsType) {
                continue;
            }

            if ($clsType == 'namespace') {
                $namespace = stripslashes($match[2][0]) . "\\";
            } else {
                if (isset($match[2][0]) && $match[2][0]) {
                    $clsName          = stripslashes($match[2][0]);
                    $clsName          = $namespace . $clsName;
                    $clsArr[$clsName] = $fileName;
                    if (isset($uriMatch[0][0]) && $uriMatch[0][0]) {
                        $uri = $uriMatch[1][0] . end($uriMatch)[0];
                        $this->appendUriRUle($clsName, $uri);
                    }
                }
            }
            $i++;
        }
        fclose($handle);

        return $clsArr;
    }
}