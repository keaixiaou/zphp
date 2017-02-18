<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/23
 * Time: 下午11:35
 */

namespace ZPHP\Coroutine\Mongo;

use ZPHP\Coroutine\Base\CoroutineBase;

class MongoCoroutine extends CoroutineBase{

    /**
     * @var $data = [
     * 'method' => 'count',
        'param' => [$this->collection, $this->filter],
     * ]
     */

    public function __construct(MongoAsynPool $mongoAsynPool)
    {
        $this->ioVector = $mongoAsynPool;
    }
}