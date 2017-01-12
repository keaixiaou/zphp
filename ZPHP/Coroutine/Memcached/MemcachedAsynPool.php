<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/6
 * Time: 下午3:00
 */

namespace ZPHP\Coroutine\Memcached;

use ZPHP\Core\Factory;
use ZPHP\Core\Log;
use ZPHP\Coroutine\Base\TaskDistribute;
use ZPHP\Coroutine\Base\TaskParam;
use ZPHP\Coroutine\Pool\AsynPool;

class MemcachedAsynPool extends AsynPool{
    protected $taskName = 'memcached';
    function __construct()
    {
        parent::__construct();
        $this->taskList = new \SplQueue();
    }


    function command($callback, $object){
        $data = [
            'execute' => $object
        ];
        $data['token'] = $this->addTokenCallback($callback);
        call_user_func([$this, 'execute'], $data);
    }


    function execute($data){
        if($this->pool->isEmpty()){
            $this->commands->enqueue($data);
            $this->prepareOne(null);
            return;
        }else{
            $client = $this->pool->dequeue();
        }
        $execute = [];
        $execute['class'] = '\ZPHP\Coroutine\Memcached\MemcachedTask';
        $execute['class_param'] = ['taskId'=>$client->taskId, 'config'=>$client->config];
        $execute['method'] = $data['execute']['method'];
        $execute['param'] = $data['execute']['param'];
        try {
            $exeRes = $this->server->task($execute, $client->taskId, function (\swoole_server $serv, $task_id, $res) use ($client, $data) {
                if(!empty($res['exception'])){
                    $data['result']['exception'] = $res['exception'];
                }else{
                    $data['result'] = $res['result'];
                }
                $this->pushToPool($client);
                call_user_func([$this, 'distribute'], $data);

            });
            if ($exeRes===false) {
                throw new \Exception("Memcached 执行失败");
            }
        } catch (\Exception $e){
            $data['result']['exception'] = $e->getMessage();
            call_user_func([$this, 'distribute'], $data);
        }
    }


    function pushToPool($client){
        $this->pool->push($client);
        if(!$this->commands->isEmpty()){
            $command = $this->commands->dequeue();
            $this->execute($command);
        }
    }

    function prepareOne($data){
        if ($this->max_count >= $this->config['asyn_max_count']) {
            return;
        }

        $this->max_count ++;
        $this->reconnect();
    }


    function reconnect(){
        if(!$this->taskList->isEmpty()) {
            $taskId = $this->taskList->dequeue();
            $client = new TaskParam($taskId, $this->config);
            $this->pushToPool($client);
        }

    }


}
