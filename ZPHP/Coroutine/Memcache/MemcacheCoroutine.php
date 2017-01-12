<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace ZPHP\Coroutine\Memcache;

use ZPHP\Coroutine\Base\CoroutineResult;
use ZPHP\Coroutine\Base\ICoroutineBase;
use ZPHP\Coroutine\Memcache\MemcacheAsynPool;

class MemcacheCoroutine implements ICoroutineBase
{
    /**
     * @var MemcacheAsynPool
     */
    public $memcacheAsynPool;
    /**
     * @var data => ['key'=>'','value'=>'','expire'=>''];
     */
    public $data;
    public $result;

    public function __construct($memcacheAsynPool)
    {
        $this->result = CoroutineResult::getInstance();
        $this->memcacheAsynPool = $memcacheAsynPool;
    }


    public function command($data){
        $this->data = $data;
        $genData = yield $this;
        return $genData;
    }


    public function send(callable $callback)
    {
        $this->memcacheAsynPool->command($callback, $this->data);
    }

    public function getResult()
    {
        return $this->result;
    }
}