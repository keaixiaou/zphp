<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/4/14
 * Time: 下午2:39
 */

namespace ZPHP\Network\Http;

use ZPHP\Core\Config;
use ZPHP\Core\Rand;
use ZPHP\Network\BaseResponse;
use ZPHP\Session\Session;

class Response extends BaseResponse{

    public $header = ['Connection'=>'keep-alive'];

    public $cookie = [];
    public $session = [];
    public $code = 200;
    protected $swResponse;

    protected function setTypeVal($type, $key, $value){
        if(is_null($value))
            unset($this->$type[$key]);
        else
            $this->$type[$key] = $value;
    }

    public function setHeader($key, $value=null){
        $this->setTypeVal('header', $key, $value);
    }

    public function setCookie($key, $value=null){
        $this->setTypeVal('cookie', $key, $value);
    }

    public function setSession($key, $value){
        $this->setTypeVal('session', $key, $value);
    }

    public function setHttpCode($code){
        $this->code = $code;
    }

    protected function responseArrayVal($type, $cacheTime=0){
        foreach($this->$type as $key => $value){
            if(empty($cacheTime))
                $this->swResponse->$type($key, $value);
            else
                $this->swResponse->$type($key, $value, time()+$cacheTime);
        }
    }


    protected function responseHeader(){
        $this->responseArrayVal('header');
    }

    protected function responseSession(){

        if(!empty(Config::getField('session', 'enable'))){
            $sid = null;
            if(!empty($this->cookie[Session::$_sessionKey]))
                $sid = $this->cookie[Session::$_sessionKey];
            if(empty($sid)){
                $sid = Rand::string(8);
                $this->cookie[Session::$_sessionKey] = $sid;
            }
            yield Session::set($this->session, $sid);
        }
        $this->responseCookie();
    }

    protected function responseCookie(){
        if(!empty($this->cookie)) {
            $this->responseArrayVal('cookie');
        }
    }

    public function finish($swResponse){
        $this->swResponse = $swResponse;
        $this->responseHeader();
        yield $this->responseSession();
        $this->swResponse->status($this->code);
        $this->swResponse->end($this->content);
    }
}