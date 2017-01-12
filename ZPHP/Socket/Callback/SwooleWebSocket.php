<?php


namespace ZPHP\Socket\Callback;

use ZPHP\Client\SwooleHttp;
use ZPHP\Core;


abstract class SwooleWebSocket extends SwooleHttp
{
    abstract public function onMessage($server, $frame);
}
