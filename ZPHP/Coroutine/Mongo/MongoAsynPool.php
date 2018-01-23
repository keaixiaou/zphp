<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/23
 * Time: 下午11:37
 */

namespace ZPHP\Coroutine\Mongo;

use ZPHP\Core\Log;
use ZPHP\Coroutine\Base\IOvector;
use ZPHP\Coroutine\Base\TaskDistribute;
use ZPHP\Coroutine\Base\TaskParam;
use ZPHP\Coroutine\Pool\AsynPool;

class MongoAsynPool extends AsynPool implements IOvector{
    protected $_asynName = 'mongo';
    function __construct()
    {
        parent::__construct();
        $this->taskList = new \SplQueue();
    }

    function execute($data)
    {
        if($this->pool->isEmpty()){
            $this->prepareOne($data);
            return;
        }else{
            $client = $this->pool->dequeue();
        }
        $execute = [];
        $execute['class'] = '\ZPHP\Coroutine\Mongo\MongoTask';
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
                throw new \Exception("Mongo 执行失败");
            }
        } catch (\Exception $e){
            $data['result']['exception'] = $e;
            $this->distribute($data);
        }
    }


    /**
     * @param callable $callback
     * @param $command
     */
    public function command(callable $callback=null, $command){
        $this->checkAndExecute(['execute'=>$command], $callback);
    }


    function reconnect($data){
        $this->commands->enqueue($data);
        if(!$this->taskList->isEmpty()) {
            $taskId = $this->taskList->dequeue();
            $client = new TaskParam($taskId, $this->config);
            $this->pushToPool($client);
        }

    }

}