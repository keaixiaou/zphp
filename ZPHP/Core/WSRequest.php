<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/21
 * Time: 下午4:16
 */

namespace ZPHP\Core;


use ZPHP\Controller\WSController;

class WSRequest extends Request{
    protected $server;
    protected $frame;
    protected $socketData;

    /**
     * 初始化
     * @param $request
     * @param $response
     */
    public function init($server, $frame){
        $this->server = $server;
        $this->frame = $frame;
        $this->socketData = json_decode($frame->data, true);
    }

    /**
     * 解析websocket
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function parse(){
        $dataTmp = $this->socketData;
        if(empty($dataTmp)){
            throw new \Exception("数据非法");
        }
        $parseConfig = Config::getField('websocket','parse');
        if(empty($parseConfig)){
            $parseConfig = [
                'module'=>'WebSocket',
                'controller' => 'Index',
                'action' => 'index',
                'field' => [
                    'data' => 'd',
                    'route' => 'm',
                ]
            ];
        }
        if(empty($parseConfig['route'][$dataTmp[$parseConfig['field']['route']]])){
            throw new \Exception("数据有误");
        }
        $mvc['module'] = $parseConfig['module'];
        $routeUrl = $parseConfig['route'][$dataTmp[$parseConfig['field']['route']]];
        $routeParse = explode('/', $routeUrl);
        $mvc['controller'] = !empty($routeParse[0])?$routeParse[0]:$parseConfig['controller'];
        $mvc['action'] = !empty($routeParse[1])?$routeParse[1]:$parseConfig['action'];
        return ['mvc'=>$mvc];
    }

    /**
     *
     * @param $mvc
     * @return mixed|string
     * @throws \Exception
     */
    public function defaultDistribute($mvc){
        $controllerClass = $mvc['module'].'\\'.$mvc['controller'];
        if(!empty(Config::getField('project','reload')) && extension_loaded('runkit')){
            App::clear($controllerClass, 'controller');
        }
        try {
            $FController = App::controller($controllerClass);
        }catch(\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        $controller = clone $FController;
        $action = $mvc['action'];
        if(!method_exists($controller, $action)){
            throw new \Exception(404);
        }
        /**
         * @var WSController $controller
         */
        $controller->coroutineMethod = function()use($controller, $action){
            return call_user_func([$controller, $action]);
        };
        $controller->setSocket($this->server, $this->frame->fd, $this->socketData);
        return $this->executeGeneratorScheduler($controller);
    }
}