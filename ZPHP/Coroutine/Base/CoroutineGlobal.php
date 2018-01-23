<?php
/**
 * Created by PhpStorm.
 * author: zhaoye(zhaoye@youzan.com)
 * Date: 2018/1/22
 * Time: 下午5:06
 */
namespace ZPHP\Coroutine\Base;

class CoroutineGlobal implements ICoroutineBase {
    protected $callback = null;

    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    public function sendCallback(callable $callback){
        $this->callback = $callback;
    }

    public function distribute(CoroutineTask $task){
        return call_user_func($this->callback, $task);
    }
}