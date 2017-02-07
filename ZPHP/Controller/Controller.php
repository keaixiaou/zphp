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

class Controller extends IController{
    public $request;
    public $response;
    protected $header = ['Connection'=>'keep-alive'];
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


    /**
     * 传入变量到模板
     * @param $name
     * @param $value
     */
    protected function assign($name, $value){
        $this->tplVar[$name] = $value;
    }


    /**
     * 设置模板
     * @param $template
     * @throws \Exception
     */
    protected function setTemplate($template){
        $this->view->setTemplate($template);
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
     * 处理请求
     * @return \Generator
     */
    public function coroutineStart(){
        yield $this->doBeforeExecute();
        $initRes = true;
        if(method_exists($this, 'init')){
            $initRes = yield $this->init();
        }
        try{
            if($this->checkResponse() && $initRes){
                $result = yield call_user_func_array($this->coroutineMethod, $this->coroutineParam);
            }
        }catch(\Exception $e){
            $this->onUserExceptionHandle($e->getMessage());
        }

        yield $this->finishRequest($result);
    }


    /**
     * 结束请求
     * @param null $result
     * @return \Generator
     */
    protected function finishRequest($result=null){

        yield $this->doBeforeDestroy();
        if($this->checkResponse()){
            if(!is_string($result) && $this->checkApi()){
                $this->jsonReturn($result);
            }else{
                $this->strReturn($result);
            }

        }
        if(!empty($this->header)){
            foreach($this->header as $key => $value){
                $this->response->header($key, $value);
            }
        }
        $this->response->end($this->responseData);
        $this->destroy();
    }

    /**
     * 异常处理
     */
    public function onUserExceptionHandle($message){
        $this->strReturn($message);
    }

    /**
     * 系统异常错误处理
     * @param $message
     */
    public function onSystemException($message){
        $message = DEBUG===true?$message:'系统出现了异常';
        $this->strReturn(Swoole::info($message), 500);
        yield $this->finishRequest();
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


    public function destroy()
    {
        parent::destroy(); // TODO: Change the autogenerated stub
        if(ob_get_contents())ob_end_clean();
        unset($this->request);
        unset($this->response);
    }

}