<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace ZPHP\Coroutine\Redis;



use ZPHP\Coroutine\Base\CoroutineResult;
use ZPHP\Coroutine\Base\ICoroutineBase;

class RedisCoroutine implements ICoroutineBase
{
    /**
     * @var RedisAsynPool
     */
    public $redisAsynPool;
    /**
     * @var data => ['key'=>'','value'=>'','expire'=>''];
     */
    public $data;
    public $result;

    public function __construct($redisAsynPool)
    {
        $this->result = CoroutineResult::getInstance();
        $this->redisAsynPool = $redisAsynPool;
    }


    public function command($data){
        $this->data = $data;
        yield $this;
    }


    public function send(callable $callback)
    {
        $this->redisAsynPool->command($callback, $this->data);
    }

    public function getResult()
    {
        return $this->result;
    }
}

class RedisNull
{

}