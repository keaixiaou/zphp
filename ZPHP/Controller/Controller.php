<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/14
 * Time: 下午5:16
 */

namespace ZPHP\Controller;

use ZPHP\Core\Config;
use ZPHP\Core\Di;
use ZPHP\Core\Factory;
use ZPHP\Core\Log;
use ZPHP\Core\Swoole;
use ZPHP\Monitor\Monitor;
use ZPHP\Network\Http\Httpinput;
use ZPHP\Network\Http\Request;
use ZPHP\Network\Http\Response;
use ZPHP\Session\Session;
use ZPHP\View\View;
use ZPHP\ZPHP;

class Controller extends IController{

    private $swRequest;
    private $swResponse;

    /**
     * @var Request
     */
    protected $request;

    protected $cookie;

    protected $session;

    /**
     * @var View $view;
     */
    protected $view;


    protected $template;
    protected $tplVar = [];
    protected $tplFile = '';
    protected $tmodule ;
    protected $tcontroller;
    protected $tmethod;


    function __construct()
    {
        $vConfig = Config::getField('project', 'view');
        $this->view = Di::make(View::class, $vConfig);
        $this->request = Di::make(Request::class);
        $this->response = Di::make(Response::class);
    }


    function __clone()
    {
        // TODO: Implement __clone() method.
        $cloneArray = ['view', 'request','response'];
        foreach($cloneArray as $item){
            if(!empty($this->$item)){
                $this->$item = clone $this->$item;
            }
        }
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
        $result = null;
        if($this->checkResponse() && $initRes){
            $result = yield call_user_func_array($this->coroutineMethod, $this->coroutineParam);
        }

        yield $this->endResponse($result);
    }



    public function setSwRequestResponse($swrequest, $swresponse){
        $this->swRequest = $swrequest;
        $this->swResponse = $swresponse;
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
//        $monitor = Container::make(\ZPHP\Monitor\Monitor::class);
        $monitor = Di::make(Monitor::class);
        $monitor->outPutWebStatus();
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }


    protected function setResponseContent($content){
        $this->response->setReponseContent($content);
    }

    protected function setStatusCode($code){
        $this->response->setHttpCode($code);
    }

    /**
     * @param $key
     * @param $value
     */
    protected function setHeader($key, $value){
        $this->response->setHeaderVal($key, $value);
    }


    /**
     * html return
     * @param $data
     */
    protected function strReturn($data, $code=Response::HTTP_OK){
        if($this->checkResponse()){
            $this->setHeader('Content-Type', 'text/html; charset=utf-8');
            $result = strval($data);
            $this->setStatusCode($code);
            $this->setResponseContent($result);
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
            $this->setStatusCode(200);
            $this->setHeader('Content-Type',   'application/json');
            $this->setResponseContent($result);
        }
    }

    /**
     * 返回图片
     * @param $content
     * @param $type
     */
    protected function image($content, $type){
        if($this->checkResponse()) {
            $this->setHeader('Content-Type', 'image/' . $type);
            $this->setStatusCode(Response::STATUS_CODE_NORMAL);
            ob_start();
            $ImageFun = 'image'.$type;
            $ImageFun($content);
            $result = ob_get_contents();
            imagedestroy($content);
            $this->setResponseContent($result);
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
        if(Config::getField('session', 'enable'))
            $this->assign('session', $this->session);
        return $this->view->fetch($this->tplVar, $tplFile);
    }


    /**
     * 跳转方法
     * @param $url
     */
    protected function redirect($url){
        $this->setHeader('Location', $url);
        $this->strReturn('', Response::HTTP_FOUND);
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
     * 结束请求
     * @param $result
     */
    protected function endResponse($result=null){
        if($this->checkResponse()){
            if(!is_string($result) && $this->checkApi()){
                $this->jsonReturn($result);
            }else{
                $this->strReturn($result);
            }
        }
        $this->response->setSession($this->session);
        $this->response->setCookie($this->cookie);
        yield $this->response->finish($this->swResponse);
    }


    /**
     * 系统异常错误处理
     * @param $message
     */
    public function onSystemException(\Exception $exception){
        $message = $exception->getMessage();
        $message = DEBUG===true?$message:'系统出现了异常';
        $this->strReturn(Swoole::info($message), Response::HTTP_INTERNAL_SERVER_ERROR);
        yield $this->endResponse();
    }


    /**
     * 全局变量的初始化
     */
    public function doBeforeExecute()
    {
        if(!empty($this->request)) {
            yield $this->request->init($this->swRequest);
            $this->cookie = $this->request->cookie;
            $this->session = $this->request->session;
        }
        if(!empty($this->view)) {
            $this->view->init([
                'module' => $this->module,
                'controller' => $this->controller,
                'method' => $this->method
            ]);
        }
    }


    public function destroy()
    {
        parent::destroy(); // TODO: Change the autogenerated stub
        unset($this->request);
        unset($this->swRequest);
        unset($this->swResponse);
    }

}