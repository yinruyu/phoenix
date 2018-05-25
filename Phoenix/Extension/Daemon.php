<?php

namespace Phoenix\Extension;

use Phoenix\Core\Exception;
use Phoenix\Core\ShellExec;
use Phoenix\Program\Utls;

class Daemon
{
    public $logFile;
    public $pidFile;
    public $user;
    public $environment;
    public $phpBin;
    public $phpIni;
    public $phpFile;
    public $phpArgs;
    public $runPath;

    private $terminate = false;
    private $childPID;
    private $hashFile;


    public function __construct(\Phoenix\DTO\Daemon $daemon)
    {
        $this->phpBin      = $daemon->phpBin;
        $this->phpIni      = $daemon->phpIni;
        $this->phpFile     = $daemon->phpFile;
        $this->environment = $daemon->environment;
        $this->user        = $daemon->user;
        $this->phpArgs     = $daemon->phpArgs;
        $this->runPath     = $daemon->runPath;
        $this->hashFile    = $this->_getSysFileName($this->runPath);
    }

    public static function exec(\Phoenix\DTO\Daemon $daemon, $cmd)
    {
        $svc = new Daemon($daemon);
        if (Daemon::isSupportCmd($cmd)) {
            $svc->$cmd();
        } else {
            ShellExec::out('daemon nothing to do !');
        }
    }

    public static function getSupportCmd()
    {
        return ['start', 'stop'];
    }

    public static function isSupportCmd($cmd)
    {
        $cmds = self::getSupportCmd();

        return in_array($cmd, $cmds);
    }

    public function getScriptArgs()
    {
        $args = is_array($this->phpArgs) ? $this->phpArgs : [$this->phpArgs];
        $args = array_merge_recursive([
            "-c",
            $this->phpIni,
            "-f",
            $this->phpFile,
        ], $args);

        return $args;
    }

    public function start()
    {
        $this->_stop(null, $this->_getPhpFileName());
        $this->_initPhpFile($this->runPath);
        $this->_work();
        ShellExec::out($this->phpFile . " run in daemon!");
    }

    public function stop()
    {
        $pidList = $this->_hashPidList();
        foreach ($pidList as $phpFile => $pid) {
            $this->_stop($pid, $phpFile);
        }
        if ($pidList) {
            file_put_contents($this->hashFile, "");
        }
        ShellExec::out("daemon do stop!");
    }

    public static function isStart($cmd)
    {
        return in_array($cmd, ['start']);
    }

    public static function isStop($cmd)
    {
        return in_array($cmd, ['stop']);
    }

    private function _work()
    {
        $daemonPID = $this->_runDaemon();
        if (!$daemonPID) return false;
        declare(ticks=1);
        $this->_registSignalHandler();
        $this->_daemonPid($daemonPID);
        pcntl_signal_dispatch();
        $this->_procRun($daemonPID);
        $this->_logMessage($daemonPID . "正常退出..");
    }

    public function signalHandler($signNO)
    {
        switch ($signNO) {
            case SIGTERM:
            case SIGHUP:
            case SIGQUIT:
            case SIGKILL:
                $this->terminate = true;
                posix_kill($this->childPID, SIGKILL);
                $this->_logMessage($signNO . "{父进程结束子进程{$this->childPID}}..");
                break;
            default:
                return false;
        }
    }

    private function _registSignalHandler()
    {
        pcntl_signal(SIGTERM, [__CLASS__, "signalHandler"], false);
        pcntl_signal(SIGHUP, [__CLASS__, "signalHandler"], false);
        pcntl_signal(SIGQUIT, [__CLASS__, "signalHandler"], false);
    }


    private function _daemonizeScript($parentPID)
    {
        $scriptPID = pcntl_fork();
        if ($scriptPID == -1) {//启动失败
            return false;
        } elseif ($scriptPID) {//父进程逻辑
            $this->_logMessage($scriptPID . "(父进程：{$parentPID})正常开启..");
            $this->childPID = $scriptPID;
            pcntl_wait($status);//进入阻塞，等待子进程退出
            if ($status === 0) {
                $this->_logMessage($scriptPID . "(父进程：{$parentPID})正常退出..");
            } else {
                if (!$this->terminate) {
                    $this->_logMessage($scriptPID . "(父进程：{$parentPID})异常退出，状态为{$status}");
                    $this->_daemonizeScript($parentPID);
                } else {
                    $this->_logMessage($scriptPID . "(父进程：{$parentPID})正常退出..");
                }
            }
        } else {
            $scriptPID = posix_getpid();
            $args      = $this->getScriptArgs();
            $this->_logMessage($scriptPID . "(父进程：{$parentPID})开始执行脚本：" . implode(" ", $args) . "..");
            pcntl_exec($this->phpBin, $args, $this->environment);
        }
    }

    private function _procRun($parentPID, $restartNum = 0)
    {
        $spec        = [
            ["pipe", "r"], //标准输入，子进程从此管道读取数据
            ["pipe", "w"], //标准输出，子进程向此管道写入数据
        ];
        $args        = $this->getScriptArgs();
        $process     = proc_open($this->phpBin . " " . implode(" ", $args), $spec, $pipes, null, $this->environment);
        $isTerminate = true;
        if (is_resource($process)) {
            stream_set_blocking($pipes[1], 0);//设置为非阻塞模式
            $procStatus = proc_get_status($process);
            $scriptPID  = $procStatus["pid"];
            $this->_logMessage($scriptPID . "(父进程：{$parentPID})正常开启..");
            if ($procStatus["running"]) {
                $this->_logMessage($procStatus["command"] . " start in " . $scriptPID);
            }
            while (true) {
                $procStatus = proc_get_status($process);
                $outTxt     = fread($pipes[1], 4096);
                if ($outTxt) {
                    $this->_logMessage($procStatus["pid"] . ":" . $outTxt);
                }
                if (!$procStatus["running"]) {
                    if ($procStatus["signaled"] && !$procStatus["stopped"]) {//如果是进程直接KILL
                        $isTerminate = false;
                        $this->_logMessage($procStatus["pid"] . "被直接KILL(signal:{$procStatus['termsig']}),立即重启");
                        break;
                    } elseif ($procStatus["signaled"] && !$procStatus["stopped"]) {
                        $isTerminate = false;
                        break;
                    } elseif (!$procStatus["signaled"] && !$procStatus["stopped"]) {
                        if (!in_array($procStatus["exitcode"], [0, -1])) {
                            $sleepSecond = $this->getSleep($restartNum);
                            $msg         = $procStatus["pid"] . "运行失败：代码异常,{$sleepSecond}秒后重启";
                            $isTerminate = false;
                            $this->mail($procStatus["command"], $msg . "\n" . $outTxt);
                            $this->_logMessage($msg);
                            sleep($sleepSecond);
                            break;
                        } else {
                            break;
                        }
                    } else {
                        break;
                    }
                }
                sleep(1);
            }
            fclose($pipes[0]);
            fclose($pipes[1]);
            $returnValue = proc_close($process);
            $this->_logMessage($scriptPID . "(父进程{$parentPID})脚本退出:" . $returnValue);
        }
        if (!$isTerminate) {
            $this->_procRun($parentPID, $restartNum + 1);
        } else {
            $this->_logMessage($parentPID . "正常退出,");
        }
    }

    private function _runDaemon()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {//启动失败
            return false;
        } elseif ($pid) {//父进程退出
            exit();
        } else {//子进程进入守护模式
            $pid = posix_getpid();

            if (!$this->_setIdentity()) {//设置守护进程所属用户
                exit();
            }
            $sid = posix_setsid();
            if ($sid < 0) {//
                $this->_logMessage($pid . "设置session leader失败");
                exit();
            }
            chdir("/");//更改程序目录为根目录
            umask(0); //清除权限
            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);

            return $pid;
        }
    }

    private function _logMessage($message)
    {
        $message = date("Y-m-d H:i:s") . "\t" . $message;
        $handle  = fopen($this->logFile, "a");
        fwrite($handle, $message . "\n");
        fclose($handle);
    }

    private function _setIdentity()
    {
        $user = posix_getpwnam($this->user);
        if (!posix_setgid($user['gid']) || !posix_setuid($user['uid'])) {
            return false;
        } else {
            return true;
        }
    }


    private function _daemonPid($pid = null)
    {
        if (!file_exists($this->pidFile)) {
            return null;
        }
        if (is_null($pid)) {
            return file_get_contents($this->pidFile);
        } else {
            $this->_hashPid($pid);
            file_put_contents($this->pidFile, $pid);
        }

        return null;
    }


    private function _hashPid($pid = null)
    {
        if (!file_exists($this->hashFile)) {
            return [];
        }
        $fileMd5    = $this->_getPhpFileName();
        $scriptData = file_get_contents($this->hashFile);
        if ($scriptData) {
            $scriptData = unserialize($scriptData);
        } else {
            $scriptData = [];
        }
        $scriptData[$fileMd5][] = $pid;
        file_put_contents($this->hashFile, serialize($scriptData));

        return null;
    }

    private function _hashPidList()
    {
        if (!file_exists($this->hashFile)) {
            return [];
        }
        $scriptData = file_get_contents($this->hashFile);
        if ($scriptData) {
            return unserialize($scriptData);
        } else {
            return [];
        }
    }

    private function _isRunDaemon($pid)
    {
        if ($pid && posix_kill(trim($pid), 0)) {
            return true;
        } else {
            return false;
        }
    }

    private function _initPhpFile($runPath)
    {
        if (!$runPath) {
            throw new Exception("runPath不能为空！");
        }
        if (!$this->phpFile) {
            throw new Exception("phpFile不能为空！");
        }
        if (!$this->hashFile) {
            throw new Exception("hashFile 没有初始化！");
        }
        $phpFileName   = $this->_getPhpFileName();
        $this->logFile = "{$runPath}/{$phpFileName}_run.log";
        $this->pidFile = "{$runPath}/{$phpFileName}_run.pid";
        if (!file_exists($this->logFile)) {
            Utls::chmodFile($this->logFile, $this->user);
        }
        if (!file_exists($this->pidFile)) {
            Utls::chmodFile($this->pidFile, $this->user);
        }
        if (!file_exists($this->hashFile)) {
            Utls::chmodFile($this->hashFile, $this->user);
        }
    }


    private function _getSysFileName($runPath)
    {
        return "{$runPath}/.hashpid";
    }

    private function _unlinkPidFile($phpFileMd5)
    {
        $file = $this->runPath . "/" . $phpFileMd5 . "_run.pid";
        if (file_exists($file)) {
            ShellExec::exec("sudo sh -c 'rm -f {$file}'");
        }
    }

    private function _getPhpFileName()
    {
        $phpFileName = substr($this->phpFile, strripos($this->phpFile, "/") + 1);

        return str_replace(".", "_", $phpFileName);
    }

    private function _stop($pid = null, $phpFileMd5 = null)
    {
        if (is_null($pid)) {
            $pid = $this->_daemonPid();
        }
        if ($phpFileMd5) {
            $this->_unlinkPidFile($phpFileMd5);
        }
        if ($pid) {
            if (is_array($pid)) {
                foreach ($pid as $vpid) {
                    if ($this->_isRunDaemon($vpid)) {
                        posix_kill($vpid, SIGHUP);
                    }
                }
            } else {
                if ($this->_isRunDaemon($pid)) {
                    posix_kill($pid, SIGHUP);
                }
            }
        }
    }

    private function getSleep($restartNum)
    {
        if ($restartNum == 0) {
            $seconds = 3;
        } elseif ($restartNum == 1) {
            $seconds = 10;
        } elseif ($restartNum == 2) {
            $seconds = 60;
        } elseif ($restartNum == 3) {
            $seconds = 60;
        } elseif ($restartNum == 4) {
            $seconds = 120;
        } elseif ($restartNum >= 5 && $restartNum <= 20) {
            $seconds = 300;
        } else {
            $seconds = 3600;
        }

        return $seconds;
    }

    private function mail($title, $body)
    {
        $toMail = isset($this->environment["MONITOR_MAIL"]) && $this->environment["MONITOR_MAIL"] ? $this->environment["MONITOR_MAIL"] : null;
        if ($toMail) {
            $subject = "=?UTF-8?B?" . base64_encode($title) . "?=";
            $ret     = mail($toMail, $subject, $body);
            $this->_logMessage("发送监控邮件-->{$toMail}：{$ret}");
        }
    }
}