<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/4/14
 * Time: 下午5:52
 */
namespace ZPHP\Network\Websocket;

use ZPHP\Network\BaseResponse;

class WebResponse extends BaseResponse{
    protected $swServer;
    protected $swFd;
    public function finish($server, $fd)
    {
        $this->swServer = $server;
        $this->swFd = $fd;
        $this->swServer->push($this->swFd, $this->content);
    }
}