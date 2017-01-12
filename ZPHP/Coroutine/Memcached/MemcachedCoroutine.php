<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace ZPHP\Coroutine\Memcached;

use ZPHP\Coroutine\Base\CoroutineResult;
use ZPHP\Coroutine\Base\ICoroutineBase;
use ZPHP\Coroutine\Memcached\MemcachedAsynPool;

class MemcachedCoroutine implements ICoroutineBase
{
    /**
     * @var MemcachedAsynPool
     */
    public $memcachedAsynPool;
    /**
     * @var data => ['key'=>'','value'=>'','expire'=>''];
     */
    public $data;
    public $result;

    public function __construct($memcachedAsynPool)
    {
        $this->result = CoroutineResult::getInstance();
        $this->memcachedAsynPool = $memcachedAsynPool;
    }


    public function command($data){
        $this->data = $data;
        $genData = yield $this;
        return $genData;
    }


    public function send(callable $callback)
    {
        $this->memcachedAsynPool->command($callback, $this->data);
    }

    public function getResult()
    {
        return $this->result;
    }
}