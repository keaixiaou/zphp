<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/30
 * Time: 下午3:33
 */

namespace ZPHP\Core;
use ZPHP\Controller\Controller;
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
     * @param Controller $controller
     * @return mixed|string
     */
    protected function executeGeneratorScheduler(Controller $controller){
        $action = 'coroutine'.(!empty($controller->isApi)?'Api':'Html').'Start';
        try{
            $generator = call_user_func([$controller, $action]);
            if ($generator instanceof \Generator) {
                $generator->controller = $controller;
                $task = clone $this->coroutineTask;
                $task->setRoutine($generator);
                $task->work($task->getRoutine());
            }else{
                return $generator;
            }
        }catch(\Exception $e){
            $this->response->status(500);
            $msg = DEBUG===true?$e->getMessage():'服务器升空了!';
            echo Swoole::info($msg);
        }
        return 'NULL';
    }
    /**
     * 默认mvc模式
     */
    public function defaultDistribute($mvc)
    {
        $controllerClass = $mvc['module'].'\\'.$mvc['controller'];
        if(!empty(Config::getField('project','reload')) && extension_loaded('runkit')){
            App::clear($controllerClass, 'controller');
        }
        $FController = App::controller($controllerClass);
        if(empty($FController)){
            throw new \Exception(404);
        }
        $controller = clone $FController;
        $action = $mvc['action'];
        if(!method_exists($controller, $action)){
            throw new \Exception(404);
        }
        /**
         * @var Controller $controller
         */
        $controller->coroutineMethod = function()use($controller, $action){
            return call_user_func([$controller, $action]);
        };
        $controller->module = $mvc['module'];
        $controller->controller = $mvc['controller'];
        $controller->method= $action;
        $controller->request = $this->request;
        $controller->response = $this->response;
        return $this->executeGeneratorScheduler($controller);
    }


    /**
     * 执行路由闭包函数generator
     * @param \Generator $generator
     * @return string
     */
    public function generatDistribute(\Closure $callback, $paramArray)
    {
        $type = Config::getField('project', 'type');
        $controllerName = '\\ZPHP\\Controller\\'.ucfirst(strval($type)).'Controller';
        $FController = Factory::getInstance($controllerName);
        $controller = clone $FController;
        $controller->coroutineMethod = $callback;
        $controller->coroutineParam = $paramArray;
        $controller->request = $this->request;
        $controller->response = $this->response;
        return $this->executeGeneratorScheduler($controller);
    }




}