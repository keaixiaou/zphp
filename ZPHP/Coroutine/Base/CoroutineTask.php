<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/14
 * Time: 下午11:15
 */


namespace ZPHP\Coroutine\Base;

use ZPHP\Controller\IController;
use ZPHP\Core\Config;
use ZPHP\Core\Di;
use ZPHP\Core\Log;

class CoroutineTask{
    /**
     * @var \Generator $routine;
     */
    protected $controller;
    protected $i;
    protected $timeTickId = 0;
    /**
     * @var Scheduler $scheduler
     */
    protected $scheduler;
    public function __construct()
    {
        $this->scheduler = Di::make(Scheduler::class);
        $this->i = 1;
    }

    /**
     * 克隆时深拷贝需要对stack克隆
     */
    public function __clone(){
        $this->scheduler = clone $this->scheduler;
    }

    /**
     * 协程调度器
     * @param \Generator $routine
     */
    public function work($exception=false){
        while (true) {
            if($exception!==true && empty($this->timeTickId))
                return;
//            Log::write("this'i : ".$this->i);
            $this->i++;
            $sign = $this->scheduler->schedule();
//            Log::write('sign:'.print_r($sign, true));
            if($sign===Signa::SNULL || $sign ===Signa::SCONTINUE)
                continue;
            else if($sign===Signa::SRETURN)
                return;
            else if ($sign===Signa::SBREAK)
                break;
            else if ($sign===Signa::SFINISH){
                $this->finish();
                return;
            }
        }
    }

    /**
     * 注入controller
     * @param IController $controller
     */
    public function setController(IController &$controller){
        $this->controller = $controller;
        $timeOut = Config::getField('project', 'timeout', 3000);
        $this->timeTickId = \swoole_timer_after($timeOut, function (){
            $this->onExceptionHandle("服务器超时!");
        });
    }


    public function finish(){
        if(!empty($this->timeTickId)){
            \swoole_timer_clear($this->timeTickId);
            $this->timeTickId = 0;
        }
        $this->scheduler->finish();
    }

    /**
     * 系统级错误
     * @param $message
     */
    public function onExceptionHandle($message){
        $this->finish();
        $action = 'onSystemException';
        Log::write('系统级错误:'.$message, Log::ERROR, true);
        $generator = call_user_func_array([$this->controller, $action], [$message]);
        if ($generator instanceof \Generator) {
            $this->setRoutine($generator);
            $this->work(true);
        }
    }

    /**
     * [isFinished 判断该task是否完成]
     * @return boolean [description]
     */
    public function isFinished()
    {
        return $this->stack->isEmpty() && !$this->routine->valid();
    }

    public function getRoutine()
    {
        return $this->scheduler->getRoutine();
    }


    public function setRoutine(\Generator $routine)
    {
        $this->i = 1;
        $this->scheduler->setRoutine($routine);
        $this->scheduler->setTask($this);
//        $this->routine = $routine;
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

}