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
    protected $_maxToken = DEBUG===true?100:650000;
    protected $_asynName;
    protected $commands;
    protected $pool;
    protected $callBacks;
    protected $workerId;
    protected $server;
    protected $swoole_server;
    protected $max_count=0;
    protected $taskNum=0;
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
        $this->commands = new \SplQueue();
        $this->pool = new \SplQueue();
    }


    protected function checkAndExecute($data, callable $callback=null){
        try {
            $this->checkTaskList();
            $data['token'] = $this->addTokenCallback($callback);
            $this->execute($data);
        }catch(\Exception $e){
            $data['result']['exception'] = $e;
            $this->callbackToCoroutine($callback, $data);
        }
    }

    public function checkTaskList(){
        if($this->taskNum >= $this->_maxToken){
            throw new \Exception("任务已满!");
        }
        if(empty($this->config['asyn_max_count'])){
            throw new \Exception("连接池数量必须大于0!");
        }
        $this->taskNum++;
    }

    public function addTokenCallback($callback)
    {
        $token = uniqid();
        $this->callBacks[$token] = $callback;
        return $token;
    }

    /**
     * 数据回调到协程主任务
     * @param $data
     */
    public function distribute($data, callable $callback=null)
    {
        if($callback===null &&!empty($data['token']) &&!empty($this->callBacks[$data['token']])){
            $callback = $this->callBacks[$data['token']];
            unset($this->callBacks[$data['token']]);
            $this->taskNum--;
        }
        if(!empty($data['result']['exception'])){
            Log::write('Coroutine Exception:'.$data['result']['exception']->getMessage(), Log::ERROR, true);
        }

        if (!empty($callback)) {
            $this->callbackToCoroutine($callback, $data);
        }
    }

    /**
     * @param callable $callback
     * @param $data
     */
    public function callbackToCoroutine(callable $callback, $data){
        if(!empty($data['result']['exception'])){
            $data['asynName'] = $this->_asynName;
            $outputExecute = $data;
            unset($outputExecute['result']['exception']);
            Log::write('Coroutine Exception:'.$data['result']['exception']->getMessage().";Execute:".print_r($outputExecute, true), Log::ERROR, true);
        }
        call_user_func_array($callback, [$data['result'], $data["execute"]]);
    }


    /**
     * @param $client
     */
    public function pushToPool($client)
    {
        $this->prepareLock = false;
        $this->pool->enqueue($client);
        if (!$this->commands->isEmpty()) {
            $command = $this->commands->dequeue();
            if(!empty($command)){
                $this->execute($command);
            }
        }
    }

    /**
     * @param $data
     */
    public function prepareOne($data){
        if ($this->max_count >= $this->config['asyn_max_count']) {
            $this->commands->enqueue($data);
            return;
        }

        $this->max_count ++;
        $this->reconnect($data);
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
    public function initTaskWorker($workerId, $server, $config){
        $this->server = $server;
        $taskList = TaskDistribute::getSingleTaskNum($this->_asynName);
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
        //
        if(!empty($this->config['max_onetime_task'])){
            $this->_maxToken = $this->config['max_onetime_task'];
        }
        $this->callBacks = [];
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