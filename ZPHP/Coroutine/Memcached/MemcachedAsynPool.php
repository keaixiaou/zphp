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
use ZPHP\Coroutine\Base\IOvector;
use ZPHP\Coroutine\Base\TaskDistribute;
use ZPHP\Coroutine\Base\TaskParam;
use ZPHP\Coroutine\Pool\AsynPool;

class MemcachedAsynPool extends AsynPool implements IOvector{
    protected $_asynName = 'memcached';
    function __construct()
    {
        parent::__construct();
        $this->taskList = new \SplQueue();
    }

    /**
     * @param callable $callback
     * @param $object
     */
    function command(callable $callback=null, $object){
        $this->checkAndExecute(['execute' => $object], $callback);
    }

    function execute($data){
        if($this->pool->isEmpty()){
            $this->prepareOne($data);
            return;
        }else{
            $client = $this->pool->dequeue();
        }
        $execute = [];
        $execute['class'] = MemcachedTask::class;
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
                $this->distribute($data);
            });
            if ($exeRes===false) {
                throw new \Exception("Memcached 执行失败");
            }
        } catch (\Exception $e){
            $data['result']['exception'] = $e;
            $this->distribute($data);
        }
    }



    public function reconnect($data){
        $this->commands->enqueue($data);
        if(!$this->taskList->isEmpty()) {
            $taskId = $this->taskList->dequeue();
            $client = new TaskParam($taskId, $this->config);
            $this->pushToPool($client);
        }

    }


}
