<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/14
 * Time: 下午11:15
 */


namespace ZPHP\Coroutine\Base;

use ZPHP\Controller\IController;
use ZPHP\Core\Log;

class CoroutineTask{
    protected $callbackData;
    protected $stack;
    /**
     * @var \Generator $routine;
     */
    protected $routine;
    protected $controller;
    protected $exception = null;
    protected $i;

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
//                if(!empty($this->exception)){
//                    throw new \Exception($this->exception);
//                }
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
                if(is_subclass_of($value, 'ZPHP\Coroutine\Base\ICoroutineBase')){
                    $this->stack->push($routine);
                    $value->send([$this, 'callback']);
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
                    $this->routine->next();
                    continue;
                }else{
                    return ;
                }
            } catch (\Exception $e) {
                while(!$this->stack->isEmpty()) {
                    $routine = $this->stack->pop();
                }
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
//        Log::write('callback:'.__METHOD__.print_r($data, true));
        if(!empty($data['exception'])){
            $this->onExceptionHandle($data['exception']);
        }else {
            $gen = $this->stack->pop();
            $this->callbackData = $data;
            $gen->send($this->callbackData);
            $this->work($gen);
        }


    }

    protected function onExceptionHandle($message){
        $action = 'onSystemException';
        $generator = call_user_func_array([$this->controller, $action], ['e'=>$message]);
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


    public function setController(IController &$controller){
        $this->controller = $controller;
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