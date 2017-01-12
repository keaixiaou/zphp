<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/26
 * Time: 上午9:37
 */


namespace ZPHP\Client;

use ZPHP\Core\Log;
use ZPHP\Socket\Callback\SwooleWebSocket as ZSwooleWebSocket;

class SwooleWebSocket extends ZSwooleWebSocket
{

    private $buff = [];

    public function onMessage($server, $frame)
    {
        Log::write('finish:'.print_r($frame, true));
        if(empty($frame->finish)) { //数据未完
            if(empty($this->buff[$frame->fd])) {
                $this->buff[$frame->fd] = $frame->data;
            } else {
                $this->buff[$frame->fd].=$frame->data;
            }
        } else {
            if(!empty($this->buff[$frame->fd])) {
                $frame->data = $this->buff[$frame->fd].$frame->data;
                unset($this->buff[$frame->fd]);
            }
        }
//        Request::parse($frame->data);
//        $result = ZRoute::route();
        Log::write('data:'.$frame->data);
        $server->push($frame->fd, $frame->data);
    }

    public function onClose(){
        $args_array = func_get_args();
        $swoole_server = $args_array[0];
        $fd = $args_array[1];
        $from_id = $args_array[2];
        Log::write('func_get_args:'.print_r($fd, true));
    }

}
