<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/30
 * Time: 下午3:33
 */

namespace ZPHP\Core;
use ZPHP\Controller\Controller;
use ZPHP\Controller\IController;
use ZPHP\Controller\WSController;
use ZPHP\Coroutine\Base\CoroutineTask;

class Request{

    /**
     * @var CoroutineTask $coroutineTask;
     */
    protected $coroutineTask;
    protected $request;
    protected $response;

    function __construct(CoroutineTask $coroutineTask)
    {
        $this->coroutineTask = $coroutineTask;
    }

    function __clone()
    {
        // TODO: Implement __clone() method.
        $this->coroutineTask = clone $this->coroutineTask;
    }

    /**
     * 初始化
     * @param $request
     * @param $response
     */
    public function init($request, $response){
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * 解析路由
     * @return array|null
     */
    public function parse(){
        return Route::parse($this->request->server['path_info'],
            $this->request->server['request_method']);
    }



    /**
     * 路由映射为闭包函数的
     * @param \Closure $callback
     * @param $param
     * @return mixed|void
     */
    public function callbackDistribute(\Closure $callback, $param)
    {
        $reflectFunc = new \ReflectionFunction($callback);
        $reflectParam = $reflectFunc->getParameters();
        $paramArray = [];
        foreach($reflectParam as $key => $value){
            if(!isset($param[$value->name])){
                break;
            }
            $paramArray[] = $param[$value->name];
        }
        $callbackResult = call_user_func_array($callback, $paramArray);
        if($callbackResult instanceof \Generator){
            $callbackResult = $this->generatDistribute($callback,$paramArray);
        }
        return $callbackResult;
    }

    /**
     * 请求最后的处理函数,协程调度器work协程任务
     * @param IController $controller
     * @return mixed|string
     */
    protected function executeGeneratorScheduler(IController $controller){
        $action = 'coroutineStart';
        $returnRes = 'NULL';
        $generator = call_user_func([$controller, $action]);
        if ($generator instanceof \Generator) {
            $task = clone $this->coroutineTask;
            $task->setController($controller);
            $task->setRoutine($generator);
            $task->work();
        }else{
            $returnRes = $generator;
        }
        return $returnRes;
    }
    /**
     * 默认mvc模式
     */
    public function defaultDistribute($mvc)
    {
        $controllerClass = $mvc['app'].'\\{type}\\'.$mvc['module'].'\\'.$mvc['controller'];
//        if(!empty(Config::getField('project','reload')) && extension_loaded('runkit')){
//            App::clear($controllerClass, 'controller');
//        }
        try {
            $controller = clone App::controller($controllerClass);
        }catch(\Exception $e) {
            throw new \Exception('404|'.$e->getMessage());
        }
        $action = $mvc['action'];
        if(!method_exists($controller, $action)){
            throw new \Exception(404);
        }
        /**
         * @var Controller $controller
         */
        $coroutineMethod = function()use($controller, $action){
            return call_user_func([$controller, $action]);
        };
        $controller->setCoroutineMethodParam($coroutineMethod, []);

        $controller->module = $mvc['module'];
        $controller->controller = $mvc['controller'];
        $controller->method= $action;
        $controller->setSwRequestResponse($this->request, $this->response);
        return $this->executeGeneratorScheduler($controller);
    }


    /**
     * 执行路由闭包函数generator
     * @param \Generator $generator
     * @return string
     */
    public function generatDistribute(\Closure $callback, $paramArray)
    {

        $FController = Factory::getInstance(\ZPHP\Controller\Controller::class);
        /**
         * @var Controller $controller
         */
        $controller = clone $FController;
        $type = Config::getField('project', 'type');
        if(strtolower($type)=='api'){
            $controller->setApi();
        }

        $controller->setCoroutineMethodParam($callback, $paramArray);
        $controller->setSwRequestResponse($this->request, $this->response);
        return $this->executeGeneratorScheduler($controller);
    }


    function __destruct()
    {
        // TODO: Implement __destruct() method.
    }


}