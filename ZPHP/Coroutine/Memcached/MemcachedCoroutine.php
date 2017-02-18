<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace ZPHP\Coroutine\Memcached;

use ZPHP\Coroutine\Base\CoroutineBase;
use ZPHP\Coroutine\Memcached\MemcachedAsynPool;

class MemcachedCoroutine extends CoroutineBase
{
    /**
     * @var command data =>
     *['method'=>'get', 'param'=>[$key]]
     */

    public function __construct(MemcachedAsynPool $memcachedAsynPool)
    {
        $this->ioVector = $memcachedAsynPool;
    }


}