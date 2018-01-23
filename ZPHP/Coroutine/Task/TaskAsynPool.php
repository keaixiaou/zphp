<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/2/23
 * Time: 下午1:52
 */
namespace ZPHP\Coroutine\Task;

use ZPHP\Coroutine\Base\IOvector;
use ZPHP\Coroutine\Base\TaskParam;
use ZPHP\Coroutine\Pool\AsynPool;

class TaskAsynPool extends AsynPool implements IOvector{
    protected $_asynName = 'task';
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
        $execute = $data['execute'];
        $execute['class_param'] = ['taskId'=>$client->taskId, 'config'=>$client->config];
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
                throw new \Exception("Task 执行失败");
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
