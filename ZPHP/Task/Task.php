<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/2/23
 * Time: 下午2:54
 */
namespace ZPHP\Task;

use ZPHP\Coroutine\Task\TaskAsynPool;
use ZPHP\Coroutine\Task\TaskCoroutine;

class Task{
    private $_taskPool;
    public function __construct(TaskAsynPool $taskAsynPool)
    {
        $this->_taskPool = $taskAsynPool;
    }

    /**
     * 不需要结果的task
     * @param $data = ['class'=>'','method'=>'','param'=>'']
     * @return bool
     */
    public function call($data){
        $taskCoroutine = new TaskCoroutine($this->_taskPool);
        $taskCoroutine->nocallback($this->changeData($data));
        return true;
    }

    /**需要task处理结果
     * $data = ['class'=>'','method'=>'','param'=>'']
     * @return $this
     */
    public function callCoroutine($data){
        $taskCoroutine = new TaskCoroutine($this->_taskPool);
        return $taskCoroutine->command($this->changeData($data));
    }

    protected function changeData($data){
        $taskData = $data;
        $taskData['param'] = [$data['param']];
        return $taskData;
    }
}