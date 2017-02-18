<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/6
 * Time: 下午3:16
 */

namespace ZPHP\Memcached;

use ZPHP\Coroutine\Memcached\MemcachedAsynPool;
use ZPHP\Coroutine\Memcached\MemcachedCoroutine;

class Memcached{
    protected $pool;
    function __construct(MemcachedAsynPool $memcachedAsynPool){
        $this->pool = $memcachedAsynPool;
    }

    function cache($key, $value='', $time_expire=3600){
        $memcachedCoroutine = new MemcachedCoroutine($this->pool);
        if($value === ''){
            $data = $memcachedCoroutine->command(['method'=>'get', 'param'=>[$key]]);
        }else{
            if(!is_null($value)) {
                $data = $memcachedCoroutine->command(['method' => 'set', 'param' => [$key, $value, $time_expire]]);
            }else{
                $data = $memcachedCoroutine->command(['method' => 'delete', 'param' => [$key]]);
            }
        }

        return $data;
    }

}