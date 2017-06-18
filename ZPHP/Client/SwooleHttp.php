<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/26
 * Time: 上午9:35
 */

namespace ZPHP\Client;

use ZPHP\Core\App;
use ZPHP\Core\Container;
use ZPHP\Core\Db;
use ZPHP\Core\DI;
use ZPHP\Core\Dispatcher;
use ZPHP\Core\Factory;
use ZPHP\Core\Config;
use ZPHP\Core\Log;
use ZPHP\Core\Request;
use ZPHP\Core\Route;
use ZPHP\Core\Swoole;
use ZPHP\Coroutine\Base\CoroutineTask;
use ZPHP\Protocol\Response;
use ZPHP\Session\Session;
use ZPHP\Socket\Callback\SwooleHttp as ZSwooleHttp;
use ZPHP\Socket\IClient;

class SwooleHttp extends ZSwooleHttp
{
    /**
     * @var Dispatcher $dispatcher
     */
    protected $dispatcher;
    /**
     * @var CoroutineTask $coroutineTask
     */
    protected $coroutineTask;

    /**
     * @var Request $requestDeal;
     */
    protected $requestDeal;

    protected $taskObjectArray;
    public function onRequest($request, $response)
    {
        try {
            if(strpos($request->server['path_info'],'.')!==false){
                throw new \Exception(403);
            }
            $requestDeal = clone $this->requestDeal;
            $requestDeal->init($request, $response);
            $httpResult = $this->dispatcher->distribute($requestDeal);
            if($httpResult!=='NULL') {
                if(!is_string($httpResult)){
                    if(strval(Config::getField('project','type'))=='api'){
                        $httpResult = json_encode($httpResult);
                    }else{
                        $httpResult = strval($httpResult);
                    }
                }
                $response->status(200);
                $response->end($httpResult);
            }
        } catch (\Exception $e) {
            $message = explode('|',$e->getMessage());
            $code = intval($message[0]);
            if($code==0){
                $response->status(500);
                $httpResult = Swoole::info($e->getMessage());
            }else {
                $response->status($code);
                $otherMessage = !empty($message[1])?' '.$message[1]:'';
                $httpResult = Swoole::info(Response::$HTTP_HEADERS[$code].$otherMessage);
            }
            $response->end($httpResult);
        }
    }



    /**
     * @param $server
     * @param $workerId
     * @throws \Exception
     */
    public function onWorkerStart($server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);
        $common = Config::getByStr('project.common_file');
        if(!empty($common)){
            require $common;
        }

        if (!$server->taskworker) {
            App::init(DI::getInstance());
            //worker进程启动协程调度器
            //work一启动加载连接池的链接、组件容器、路由
            Db::init($server, $workerId);
            Route::init();
            Session::init();
            $this->coroutineTask = Container::Coroutine('Base/CoroutineTask');
            $this->dispatcher = Container::Core('Dispatcher');
            $this->requestDeal = Container::Core('Request', $this->coroutineTask);
        }
    }


    /**
     * @param $server
     * @param $workerId
     */
    public function onWorkerStop($server, $workerId){
        if(!$server->taskworker) {
            Db::getInstance()->freeMysqlPool();
            Db::getInstance()->freeRedisPool();
        }
        parent::onWorkerStop($server, $workerId);
    }


    public function onWorkerError($server, $workerId, $workerPid, $errorCode)
    {
        //errorCode可以用来警报 或者其他的定制化
        parent::onWorkerError($server, $workerId, $workerPid, $errorCode);
    }

    public function onTask($server, $taskId, $fromId, $data)
    {
        try{
            $checkParam = ['class', 'method'];
            foreach($checkParam as $p){
                if(empty($data[$p])){
                    throw new \Exception($p." can't be empty!");
                }
            }
            if(empty($this->taskObjectArray[$data['class']])){
                $classParam = !empty($data['class_param'])?$data['class_param']:null;
                $data['class'] = str_replace('/','\\', $data['class']);
                $this->taskObjectArray[$data['class']] = Factory::getInstance($data['class'],$classParam);
                $taskObject = $this->taskObjectArray[$data['class']];
                if(method_exists($taskObject, 'init')){
                    call_user_func([$taskObject, 'init']);
                }
            }else{
                $taskObject = $this->taskObjectArray[$data['class']];
            }
            $res = call_user_func_array([$taskObject, $data['method']], $data['param']);
            return ['result'=>$res];
        }catch(\Exception $e){
            return ['exception'=>$e->getMessage()];
        }
    }

}
