<?php
/*_logMessage(date("Y-m-d H:i:s") . "__" . json_encode($_SERVER));
exit;*/
while (true) {
    echo "ok\n";
    sleep(2);
}
#_logMessage(date("Y-m-d H:i:s") . "__" .php_ini_loaded_file());
function _logMessage($message)
{
    $file   = "/home/{$_SERVER['USER']}/devspace/phoenix/runtest/api_daemon.log";
    $handle = fopen($file, "a");
    fwrite($handle, $message . "\n");
    fclose($handle);
}