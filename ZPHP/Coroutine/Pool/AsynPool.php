<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/14
 * Time: 下午2:55
 */


namespace ZPHP\Coroutine\Pool;


use ZPHP\Core\Log;
use ZPHP\Coroutine\Base\TaskDistribute;

abstract class AsynPool implements IAsynPool
{
    const MAX_TOKEN = DEBUG===true?100:650000;
    protected $commands;
    protected $pool;
    protected $callBacks;
    protected $workerId;
    protected $server;
    protected $swoole_server;
    protected $token = 0;
    protected $max_count=0;
    protected $taskName;
    //存储taskwork进程编号
    /**
     * @var \SplQueue $taskList
     */
    protected $taskList;

    //避免爆发连接的锁
    protected $prepareLock = false;
    /**
     * @var AsynPoolManager
     */
    protected $asyn_manager;
    /**
     * @var Config
     */
    protected $config;

    public function __construct()
    {
        $this->callBacks = new \SplFixedArray(self::MAX_TOKEN);
        $this->commands = new \SplQueue();
        $this->pool = new \SplQueue();
    }

    public function addTokenCallback($callback)
    {
        $token = $this->token;
        $this->callBacks[$token] = $callback;
        $this->token++;
        if ($this->token >= self::MAX_TOKEN) {
            $this->token = 0;
        }
        return $token;
    }

    /**
     * 分发消息
     * @param $data
     */
    public function distribute($data)
    {
        $callback = $this->callBacks[$data['token']];
        unset($this->callBacks[$data['token']]);
        if ($callback != null) {
            if(!empty($data['result']['exception'])){
                Log::write('Exception:'.$data['result']['exception'].";Execute:".print_r($data, true));
            }
            call_user_func_array($callback, ['data'=>$data['result']]);
        }
    }


    /**
     * 清空连接池
     */
    protected function clearPool(){
        while(!$this->pool->isEmpty()){
            $client = $this->pool->dequeue();
            unset($client);
            $this->max_count -- ;
        }
    }

    /**
     * 清空命令
     */
    protected function clearCommand(){
        while(!$this->commands->isEmpty()){
            $command = $this->commands->dequeue();
            unset($command);
        }
    }

    /**
     * 清空callback
     */
    protected function clearCallbak(){
        unset($this->callBacks);
    }


    /**
     * task 类异步
     * @param $workId
     * @param $config
     * @param $server
     */
    public function initTaskWorker($workerId, $config, $server){
        $this->server = $server;
        $taskList = TaskDistribute::getSingleTaskNum($this->taskName);
        if(!empty($taskList)){
            $myWorkTaskList = $taskList[$workerId];
            foreach ($myWorkTaskList as $taskId) {
                $this->taskList->enqueue($taskId);
            }
            $this->initWorker($workerId, $config);
        }

    }

    /**
     * @param $workerid
     */
    public function initWorker($workerId, $config)
    {
        $this->config = $config;
        $this->workerId = $workerId;
        if(!empty($this->config['start_count'])) {
            $i = 0;
            $start_count = $this->config['start_count'] > $this->config['asyn_max_count'] ?
                $this->config['asyn_max_count'] : $this->config['start_count'];
            while ($i < $start_count) {
                $this->prepareOne(null);
                $i++;
            }
        }
    }

    /**
     * @param $client
     */
    public function pushToPool($client)
    {
        $this->prepareLock = false;
        $this->pool->push($client);
        if (!$this->commands->isEmpty()) {
            $command = $this->commands->dequeue();
            $this->execute($command);
        }
    }

    /**
     * 释放连接池
     */
    public function free(){
        $this->clearPool();
        $this->clearCallbak();
        $this->clearCommand();
    }
}