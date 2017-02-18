<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace ZPHP\Coroutine\Redis;

use ZPHP\Coroutine\Base\CoroutineBase;
use ZPHP\Coroutine\Base\CoroutineResult;

class RedisCoroutine extends CoroutineBase{

    /**
     * @var data => ['key'=>'','value'=>'','expire'=>''];
     */
    public function __construct(RedisAsynPool $redisAsynPool)
    {
        $this->ioVector = $redisAsynPool;
    }

}
