<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/1
 * Time: 上午11:03
 */

namespace ZPHP\Core;
use ZPHP\Common\Utils;
use ZPHP\Session\Session;

/**
 * 关于http的输入,如get,post,session,cookie参数
 * Class Httpinput
 * @package ZPHP\Core
 */
class Httpinput{

    public $post;
    public $get;
    public $request;
    public $session;
    public $cookie;
    public $files;
    public $server;

    function __construct()
    {

    }

    public function init($request, $response){
        //获取session
        if(!empty(Config::getField('session','enable'))) {
            $this->session = yield Session::get($request, $response);
        }
        //传入请求参数
        if(!empty($request->cookie))$this->cookie = $request->cookie;
        $this->post = !empty($request->post)?$request->post:[];
        $this->get = !empty($request->get)?$request->get:[];
        $methodType = $request->server['request_method'];
        $this->request = $methodType=='GET'?array_merge($this->get, $this->post):array_merge($this->post, $this->get);
        $this->files = !empty($request->files)?$request->files:[];
        $this->server = !empty($request->server)?$request->server:[];
    }

    /**
     * @param $key
     * @param bool $filter
     * @return string
     */
    public function __call($method, $param){
        if(empty($param)){
            return $this->$method;
        }else {
            return isset($this->$method[$param[0]])?$this->_getHttpVal($this->$method[$param[0]], isset($param[1]) ? $param[1]:true ):null;
        }
    }


    /**
     * @param $variableArray
     * @param $key
     * @param $filter
     * @return string
     */
    protected function _getHttpVal($value, $filter){
        if(!isset($value))
            return null;
        if($filter)
            return Utils::filter($value);
        else
            return $value;
    }

}