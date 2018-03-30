<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/5/9
 * Time: 下午4:54
 */
namespace ZPHP\Coroutine\Base;

use ZPHP\Core\Log;
use ZPHP\Extend\DebugTrace;

class Scheduler{
    /**
     * @var CoroutineTask $coroutineTask
     */
    protected $coroutineTask;
    protected $stack;
    protected $i;
    protected $handleArray;

    public function __construct()
    {
        $this->stack = new \SplStack();
        $this->i = 1;
    }

    public function __clone()
    {
        $this->stack = clone $this->stack;
        $this->handleArray = [
            [$this, "handleGenerator"],
            [$this, "handleGlobal"],
            [$this, 'handleAsyIo'],
            [$this, 'handleNull'],
            [$this, 'handleStack'],
            [$this, 'handleValid']
        ];
    }

    public function getStack(){
        return $this->stack;
    }

    public function setTask(CoroutineTask $task){
        $this->coroutineTask = $task;
    }


    public function setCallbackData($value){
        $this->callbackData = $value;
    }

    public function schedule(){
        $sign = Signa::SNULL;
        do{
            try {
                $routine = $this->coroutineTask->getRoutine();
                if (!$routine) {
                    return;
                }
                $value = $routine->current();
                foreach ($this->handleArray as $handle){
                    $sign = call_user_func_array($handle, [$routine, $value]);
                    if($sign!==Signa::SNULL) break;
                }
            } catch (\Exception $e) {
                $this->coroutineTask->onExceptionHandle($e);
                $sign = Signa::SBREAK;
                break;
            }
        }while(false);

        return $sign;
    }

    protected function handleGenerator($routine, $value) {
        $sign = Signa::SNULL;
        //嵌套的协程
        if ($value instanceof \Generator) {
//            Log::write('嵌套');
            $this->stack->push($routine);
            $this->coroutineTask->setRoutine($value);
            $sign = Signa::SCONTINUE;
        }
        return $sign;

    }

    protected function handleGlobal($routine, $value){
        $sign = Signa::SNULL;
//        异步IO的父类
        if(is_object($value) && $value instanceof CoroutineGlobal){
            $sign = $value->distribute($this->coroutineTask);
        }
        return $sign;
    }

    protected function handleAsyIo($routine, $value){
        $sign = Signa::SNULL;
        //异步IO的父类
        if(is_object($value) && is_subclass_of($value, ICoroutineBase::class)){
//            Log::write('AsyIo');
            /**
             * @var $debugTrace DebugTrace
             */
            $debugTrace = $this->coroutineTask->getContext()->get(DebugTrace::Name);
            $debugTrace->addParam($value->getDebugKey(), $value->getDebugTrace());
            $this->stack->push($routine);
            $value->sendCallback([$this, "callback"]);
            $sign = Signa::SRETURN;
        }
        return $sign;
    }


    protected function handleNull($routine, $value){
        $sign = Signa::SNULL;
        if(is_null($value)) {
            try {
                $return = $routine->getReturn();
            } catch (\Exception $e) {
                $return = 'NULL';
            }
            if ($return !== 'NULL') {
                $this->coroutineTask->setCallbackData($return);
            }
//            Log::write('return:'.json_encode($return));
        }else {
            $routine->send($value);
            $this->coroutineTask->setCallbackData(null);
            $sign = Signa::SCONTINUE;
        }
        return $sign;
    }

    protected function handleStack($routine, $value){
        $sign = Signa::SNULL;
        if (!$this->stack->isEmpty()) {
//            Log::write('$this->stack->pop();'.print_r($this->stack, true));
            $this->coroutineTask->setRoutine($this->stack->pop());
            $this->coroutineTask->send($this->coroutineTask->getCallbackData());
            $this->coroutineTask->setCallbackData(null);
            $sign = Signa::SCONTINUE;
        }
        return $sign;
    }


    protected function handleValid($routine, $value){
        $sign = Signa::SNULL;
        if ($this->coroutineTask->getRoutine()->valid()) {
            $routine = $this->coroutineTask->getRoutine();
            $routine->next();
            $sign = Signa::SCONTINUE;
        }else{
            $this->coroutineTask->finish();
            $sign = Signa::SFINISH;
        }
        return $sign;
    }


    /**
     * [callback description]
     * @param  [type]   $r        [description]
     * @param  [type]   $key      [description]
     * @param  [type]   $calltime [description]
     * @param  [type]   $res      [description]
     * @return function           [description]
     */
    public function callback($data,$execute)
    {
        /**
         * @var $debugTrace DebugTrace
         */
        $debugTrace = $this->coroutineTask->getContext()->get(DebugTrace::Name);
        $debugTrace->addResult(md5(serialize($execute)),  $data);
        /*
         *  继续work的函数实现 ，栈结构得到保存
         */
        if(!empty($data['exception'])){
            $this->coroutineTask->onExceptionHandle($data['exception']);
        }else {
            if(!$this->stack->isEmpty()) {
                $gen = $this->stack->pop();
                $this->coroutineTask->setRoutine($gen);
                $this->coroutineTask->send($data);
                $this->coroutineTask->work();
            }
        }
    }


    public function finish(){
        while(!$this->stack->isEmpty()) {
            $routine = $this->stack->pop();
        }
    }
}