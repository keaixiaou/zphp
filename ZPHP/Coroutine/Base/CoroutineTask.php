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
use ZPHP\Core\Log;

class CoroutineTask{
    protected $callbackData;
    protected $stack;
    /**
     * @var \Generator $routine;
     */
    protected $routine;
    protected $controller;
    protected $i;
    protected $timeTickId = 0;

    public function __construct()
    {
        $this->stack = new \SplStack();
        $this->i = 1;
    }

    /**
     * 克隆时深拷贝需要对stack克隆
     */
    public function __clone(){
        $this->stack = clone $this->stack;
    }

    /**
     * 协程调度器
     * @param \Generator $routine
     */
    public function work(\Generator $routine){
        while (true) {
//            Log::write("this'i : ".$this->i);
            $this->i++;
            try {
                if (!$routine) {
                    return;
                }
                $value = $routine->current();
//                Log::write('value:'.__METHOD__.print_r($value, true));
                //嵌套的协程
                if ($value instanceof \Generator) {
//                    Log::write('嵌套');
                    $this->stack->push($routine);
                    $routine = $value;
                    continue;
                }

                //异步IO的父类
                if(is_object($value) && is_subclass_of($value, 'ZPHP\Coroutine\Base\ICoroutineBase')){
                    $this->stack->push($routine);
                    $value->sendCallback([$this, 'callback']);
                    return;
                }


                if(is_null($value)) {
                    try {
                        $return = $routine->getReturn();
                    } catch (\Exception $e) {
                        $return = 'NULL';
                    }
                    if ($return !== 'NULL') {
                        $this->callbackData = $return;
                    }
//                    Log::write('return:'.json_encode($return));
                }else {
                    $this->callbackData = $value;
                    $routine->send($this->callbackData);
                    $this->callbackData = null;
                    continue;
                }
                if (!$this->stack->isEmpty()) {
//                    Log::write('$this->stack->pop();'.print_r($this->stack, true));
                    $routine = $this->stack->pop();
                    $routine->send($this->callbackData);
                    $this->callbackData = null;
                    continue;
                }
                if ($this->routine->valid()) {
                    $routine = $this->routine;
                    $routine->next();
                    continue;
                }else{
                    $this->finishTask();
                    return ;
                }
            } catch (\Exception $e) {
                $this->onExceptionHandle($e->getMessage());

                break;
            }
        }
    }
    /**
     * [callback description]
     * @param  [type]   $r        [description]
     * @param  [type]   $key      [description]
     * @param  [type]   $calltime [description]
     * @param  [type]   $res      [description]
     * @return function           [description]
     */
    public function callback($data)
    {
        /*
            继续work的函数实现 ，栈结构得到保存
         */
        if(!empty($data['exception'])){
            $this->onExceptionHandle($data['exception']);
        }else {
            if(!$this->stack->isEmpty()) {
                $gen = $this->stack->pop();
                $this->callbackData = $data;
                $gen->send($this->callbackData);
                $this->work($gen);
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


    protected function finishTask(){
        if(!empty($this->timeTickId)){
            \swoole_timer_clear($this->timeTickId);
            $this->timeTickId = 0;
        }
    }

    /**
     * 系统级错误
     * @param $message
     */
    protected function onExceptionHandle($message){
        $this->finishTask();
        while(!$this->stack->isEmpty()) {
            $routine = $this->stack->pop();
        }
        $action = 'onSystemException';
        Log::write('系统级错误:'.$message, Log::ERROR, true);
        $generator = call_user_func_array([$this->controller, $action], [$message]);
        if ($generator instanceof \Generator) {
            $this->setRoutine($generator);
            $this->work($this->getRoutine());
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
        return $this->routine;
    }


    public function setRoutine(\Generator $routine)
    {
        $this->routine = $routine;
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

}