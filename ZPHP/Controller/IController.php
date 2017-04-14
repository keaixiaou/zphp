<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/21
 * Time: 下午2:55
 */

namespace ZPHP\Controller;

use ZPHP\Network\BaseResponse;

abstract class IController{

    public $module;
    public $controller;
    public $method;
    public $coroutineMethod;
    public $coroutineParam=[];


    /**
     * @var BaseResponse
     */
    protected $response;

    protected function init(){
        return true;
    }

    public function setApi(){
        $this->response->setApi();
    }

    public function checkApi(){
        return $this->response->checkApi();
    }

    /**
     * 异常处理
     */
    public function onExceptionHandle($message){

    }

    public function onSystemException($message){

    }

    /**
     * 返回null 替换
     * @access protected
     * @return String
     */
    protected function strNull($str){
        return str_replace(array('NULL', 'null'), '""', $str);
    }


    /**
     * 检测response是否结束
     * @return bool
     */
    protected function checkResponse(){
        return $this->response->checkResponse();
    }

    public function destroy(){
        unset($this->coroutineMethod);
        unset($this->response);
    }


    function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

}