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
use ZPHP\Core\Context;
use ZPHP\Core\Di;
use ZPHP\Core\Log;
use ZPHP\Extend\DebugTrace;
use ZPHP\Network\Http\Response;

class CoroutineTask{

    private $callbackData;
    /**
     * @var \Generator $routine;
     */
    private $routine;
    protected $i;
    protected $timeTickId = 0;
    protected $exceptFunction;
    protected $taskId = 0;
    /**
     * @var Scheduler $scheduler
     */
    protected $scheduler;
    /**
     * @var Context $context
     */
    protected $context;

    public function __construct()
    {
        $this->scheduler = Di::make(Scheduler::class);
        $this->context = Di::make(Context::class);
        $this->i = 1;
    }

    /**
     * 克隆时深拷贝需要对stack克隆
     */
    public function __clone(){
        $this->scheduler = clone $this->scheduler;
        $this->exceptFunction = [$this, "selfFuntion"];
        $this->taskId = empty($this->taskId)?TaskId::create():$this->taskId;
        $this->context = clone $this->context;
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
    public function onExceptionHandle(\Exception $exception){
        $this->finish();
        Log::write('系统级错误:'.$exception->getMessage(), Log::ERROR, true);
        if(!empty($this->exceptFunction)){
            $generator = call_user_func_array($this->exceptFunction, [$exception]);
            if ($generator instanceof \Generator) {
                $this->setRoutine($generator);
                unset($this->exceptFunction);
                $this->work(true);
            }
        }

    }

    public function getRoutine()
    {
        return $this->routine;
    }

    public function setRoutine($routine){
        $this->routine = $routine;
    }

    public function getCallbackData(){
        return $this->callbackData;
    }

    public function setCallbackData($value){
        $this->callbackData = $value;
    }

    public function setTask(\Generator $routine, $exceptFunction = null)
    {
        $this->i = 1;
        $timeOut = Config::getField('project', 'timeout', 3000);
        $this->timeTickId = \swoole_timer_after($timeOut, function (){
            $exception = new \Exception("服务器超时!", Response::HTTP_GATEWAY_TIMEOUT);
            $this->onExceptionHandle($exception);
        });
        $this->routine = $routine;
        $this->scheduler->setTask($this);
        if(!empty($exceptFunction))
            $this->setExceptionHandle($exceptFunction);
    }

    public function getContext(){
        return $this->context;
    }

    public function send($value){
        try {
            $this->routine->send($value);
            $this->callbackData = $value;
        }catch (\Exception $e){
            $this->onExceptionHandle($e);
        }
    }

    protected function setExceptionHandle($exceptFunction){
        $this->exceptFunction = $exceptFunction;
    }

    public function selfFuntion(\Exception $exception){
        sysEcho("系统异常:".$exception->getMessage());
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

}