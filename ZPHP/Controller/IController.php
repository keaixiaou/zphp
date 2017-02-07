<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/21
 * Time: 下午2:55
 */

namespace ZPHP\Controller;

use ZPHP\Core\Log;

class IController{

    /**
     * @var $response
     */
    public $isApi=false;

    protected $hasResponse=false;
    protected $responseData='';

    public $module;
    public $controller;
    public $method;
    public $coroutineMethod;
    public $coroutineParam=[];


    /**
     * controller初始操作,可用于后期加入中间介等
     * 必须返回true才会执行后面的操作
     * @return bool
     *
     */
    protected function init(){
        return true;
    }

    public function setApi(){
        $this->isApi = true;
    }

    public function checkApi(){
        return $this->isApi;
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
     * response已经被设置
     */
    protected function setResponse(){
        if($this->hasResponse){
            Log::write("ResponseData has been set!", Log::WARN);
        }
        $this->hasResponse = true;
    }

    /**
     * 检测response是否结束
     * @return bool
     */
    protected function checkResponse(){
        if($this->hasResponse){
            return false;
        }else {
            return true;
        }
    }

    public function destroy(){
        unset($this->coroutineMethod);
    }


    function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

}