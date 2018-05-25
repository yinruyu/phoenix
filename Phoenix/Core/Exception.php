<?php

namespace Phoenix\Core;

class Exception extends \Exception
{
    public function __construct($message)
    {
        echo $this->red($message);
    }

    private function red($message)
    {
        return "\033[0;31m error  {$message}\033[0m\n";
    }
}
