<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/14
 * Time: 下午5:16
 */

namespace ZPHP\Controller;

use ZPHP\Core\Config;
use ZPHP\Core\Factory;
use ZPHP\Core\Httpinput;
use ZPHP\Core\Log;
use ZPHP\Core\Request;
use ZPHP\Core\Swoole;
use ZPHP\Monitor\Monitor;
use ZPHP\Session\Session;
use ZPHP\View\View;
use ZPHP\ZPHP;

class Controller {
    /**
     * @var $response
     */
    public $isApi=false;
    /**
     * @var Request $requestDeal;
     */
    public $requestDeal;
    public $request;
    protected $hasResponse=false;
    protected $responseData='';
    public $response;
    public $module;
    public $controller;
    public $method;
    protected $template;
    protected $tplVar = [];
    protected $tplFile = '';
    protected $tmodule ;
    protected $tcontroller;
    protected $tmethod;
    /**
     * @var View
     */
    protected $view;
    public $coroutineMethod;
    public $coroutineParam=[];
    /**
     * @var Httpinput $input;
     */
    public $input;


    function __construct()
    {
        $vConfig = Config::getField('project', 'view');
        $this->view = Factory::getInstance(\ZPHP\View\View::class, $vConfig);
        $this->input = Factory::getInstance(\ZPHP\Core\Httpinput::class);
    }


    function __clone()
    {
        // TODO: Implement __clone() method.
        $cloneArray = ['view', 'input'];
        foreach($cloneArray as $item){
            if(!empty($this->$item)){
                $this->$item = clone $this->$item;
            }
        }
    }

    /**
     * controller初始操作,可用于后期加入中间介等
     * 必须返回true才会执行后面的操作
     * @return bool
     *
     */
    protected function init(){
        return true;
    }



    /**
     * 处理请求
     * @return \Generator
     */
    public function coroutineStart(){
        yield $this->doBeforeExecute();
        $initRes = true;
        if(method_exists($this, 'init')){
            $initRes = yield $this->init();
        }
        if($initRes){
            $result = yield call_user_func_array($this->coroutineMethod, $this->coroutineParam);
        }
        yield $this->doBeforeDestroy();
        if(!empty($result) && $this->checkResponse()){
            if(!is_string($result) && $this->checkApi()){
                $this->jsonReturn($result);
            }else{
                $this->strReturn($result);
            }

        }
//        Log::write('response:'.$this->responseData);
        $this->response->header('Connection','keep-alive');
        $this->response->end($this->responseData);
        $this->destroy();
    }

    /**
     * 获取自身服务状态
     * @return string
     */
    public function getNowServiceStatus(){
        ob_start();
        /**
         *  @var Monitor $monitor;
         */
        $monitor = Factory::getInstance(\ZPHP\Monitor\Monitor::class);
        $monitor->outPutWebStatus();
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }




    /**
     * 返回图片
     * @param $content
     * @param $type
     */
    protected function image($content, $type){
        if($this->checkResponse()) {
            $this->response->header('Content-Type', 'image/' . $type);
            $this->response->status(200);
            ob_start();
            $ImageFun = 'image'.$type;
            $ImageFun($content);
            $result = ob_get_contents();
            imagedestroy($content);
            $this->responseData = $result;
            $this->setResponse();
        }
    }


    /**
     * html return
     * @param $data
     */
    protected function strReturn($data, $code=200){
        if($this->checkResponse()){
            $this->response->header('Content-Type', 'text/html');
            $result = strval($data);
            $this->response->status($code);
            $this->responseData = $result;
            $this->setResponse();
        }
    }


    /**
     * json return
     * @param $data
     * @throws \Exception
     */
    protected function jsonReturn($data){
        if($this->checkResponse()) {
            $result = json_encode($data);
            if (!empty(Config::get('response_filter'))) {
                $result = $this->strNull($result);
            }
            $this->response->status(200);
            $this->response->header('Content-Type', 'application/json');
            $this->responseData = $result;
            $this->setResponse();
        }
    }


    public function setApi(){
        $this->isApi = true;
    }

    public function checkApi(){
        return $this->isApi;
    }

    /**
     * 检测response是否结束
     * @return bool
     */
    protected function checkResponse(){
        if($this->hasResponse){
            Log::write("ResponseData has been set!", Log::WARN);
            return false;
        }
        return true;
    }


    /**
     * response已经被设置
     */
    protected function setResponse(){
        $this->hasResponse = true;
    }

    protected function setTemplate($template){
        $this->view->setTemplate($template);
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
     * 全局变量的初始化
     */
    public function doBeforeExecute()
    {
        if(!empty($this->input)) {
            yield $this->input->init($this->request, $this->response);
        }
        if(!empty($this->view)) {
            $this->view->init(['module' => $this->module, 'controller' => $this->controller, 'method' => $this->method]);
        }
    }

    /**
     * 请求结束前做的一些处理,如session和cookie的写入
     * @throws \Exception
     */
    protected function doBeforeDestroy(){
        if(!empty($this->input)){
            if(!empty(Config::getField('session', 'enable'))){
                yield Session::set($this->input->session(), $this->request, $this->response);
            }
            if(!empty(Config::getField('cookie', 'enable'))){
                $cacheExpire = Config::getField('cookie', 'cache_expire', 3600);
                if(!empty($this->input->cookie)) {
                    foreach ($this->input->cookie() as $key => $value) {
                        $this->response->cookie($key, $value, time() + $cacheExpire);
                    }
                }
            }
        }

    }


    /**
     * 传入变量到模板
     * @param $name
     * @param $value
     */
    protected function assign($name, $value){
        $this->tplVar[$name] = $value;
    }


    /**
     * 获取当前请求对应html的内容
     * @param string $tplFile
     * @return mixed
     * @throws \Exception
     */
    public function fetch($tplFile=''){
        $this->assign('session', $this->input->session());
        return $this->view->fetch($this->tplVar, $tplFile);
    }


    /**
     * 跳转方法
     * @param $url
     */
    protected function redirect($url){
        $this->response->header('Location', $url);
        $this->strReturn('', 302);
    }


    /**
     * 载入模板文件
     * @param string $tplFile
     */
    public function display($tplFile=''){
        $content = $this->fetch($tplFile);
        $this->strReturn($content);
    }


    /**
     * 异常处理
     */
    public function onExceptionHandle(\Exception $e){
        $msg = DEBUG===true?$e->getMessage():'服务器暂时故障了';
        $this->response->status(500);
        $this->response->end(Swoole::info($msg));
        $this->destroy();
    }
    /**
     * 系统异常错误处理
     * @param $message
     */
    public function onSystemException($message){
        $message = DEBUG===true?$message:'系统出现了异常';
        $this->response->status(500);
        $this->response->end(Swoole::info($message));
        $this->destroy();
    }

    public function destroy(){
        if (ob_get_contents()) ob_end_clean();
        unset($this->coroutineMethod);
        unset($this->request);
        unset($this->response);
    }


    function __destruct()
    {
        // TODO: Implement __destruct() method.
    }
}