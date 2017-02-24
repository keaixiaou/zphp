<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/2/17
 * Time: 下午6:18
 */

namespace ZPHP\Coroutine\Base;

abstract class CoroutineBase implements ICoroutineBase{

    /**
     * @var IOvector
     */
    protected $ioVector;
    protected $data;

    /**
     * @param $data = ['sql'=>$sql,'trans_id'=>$trans_id]
     * @return $this
     */
    public function command($data){
        $this->data = $data;
        return $this;
    }


    /**
     * 协程调度器设置回调函数
     * @param callable $callback
     */
    function sendCallback(callable $callback){
        $this->ioVector->command($callback, $this->data);
    }
}