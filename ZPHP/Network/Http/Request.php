<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/1
 * Time: 上午11:03
 */

namespace ZPHP\Network\Http;
use ZPHP\Common\Utils;
use ZPHP\Core\Config;
use ZPHP\Core\Rand;
use ZPHP\Coroutine\Base\CoroutineGlobal;
use ZPHP\Extend\DebugTrace;
use ZPHP\Session\Session;

/**
 * 关于http的输入,如get,post,session,cookie参数
 * Class Http Request
 * @package ZPHP\Network\Http
 */
class Request{

    public $post;
    public $header;
    public $get;
    public $request;
    public $session;
    public $cookie;
    public $files;
    public $server;

    function __construct()
    {

    }

    public function init($request){
        //cookie
        if(!empty($request->cookie))$this->cookie = $request->cookie;
        //获取session
        if(!empty(Config::getField('session','enable'))) {
            $sid = !empty($this->cookie[Session::$_sessionKey])?
                $this->cookie[Session::$_sessionKey]:Rand::string(8);
            $this->cookie[Session::$_sessionKey] = $sid;
            $this->session = yield Session::get($sid);
        }
        $this->header = $request->header;
        foreach ($this->header as $key => $value){
            yield setContext($key, $value);
        }

        //传入请求参数

        $this->post = !empty($request->post)?$request->post:[];
        parse_str($request->rawContent(), $content);
        $this->post = array_merge($content, $this->post);

        $this->get = !empty($request->get)?$request->get:[];
        $methodType = $request->server['request_method'];
        $this->request = $methodType=='GET'?array_merge($this->get, $this->post):array_merge($this->post, $this->get);
        foreach ($this->request as $key => $value){
            yield setContext($key, $value);
        }

        $this->files = !empty($request->files)?$request->files:[];
        $this->server = !empty($request->server)?$request->server:[];
        foreach($this->server as $key => $value){
            yield setContext($key, $value);
        }

        $debugKey = Config::getField("project", "debug_trace", "debug_trace");
        $debugValid = yield getContext($debugKey);
        $valid = (!empty($debugValid)&&($debugValid==="t1"||$debugValid=="t2"))?true:false;
        yield setContext(DebugTrace::Valid, $valid);
        yield setContext(DebugTrace::Name, new DebugTrace($valid, $debugValid));

    }

    /**
     * @param $key
     * @param bool $filter
     * @return string
     */
    public function __call($method, $param){
        if(empty($param)){
            return empty($this->$method)?null:$this->$method;
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