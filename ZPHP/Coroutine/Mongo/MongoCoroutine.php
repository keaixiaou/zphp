<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/23
 * Time: 下午11:35
 */

namespace ZPHP\Coroutine\Mongo;

use ZPHP\Coroutine\Base\ICoroutineBase;

class MongoCoroutine implements ICoroutineBase{

    /**
     * @var MongoAsynPool
     */
    public $mongoAsynPool;
    public $object;
    public function __construct($mongoAsynPool)
    {
        $this->mongoAsynPool = $mongoAsynPool;
    }

    public function query($data){
        $this->object = $data;
        $data = yield $this;
        return $data;
    }

    public function send(callable $callback)
    {
        // TODO: Implement send() method.
        $this->mongoAsynPool->query($callback, $this->object);
    }

}