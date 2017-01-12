<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/6
 * Time: 下午3:16
 */

namespace ZPHP\Memcached;

use ZPHP\Coroutine\Memcached\MemcachedCoroutine;

class Memcached{
    protected $pool;
    protected $_memcachedCoroutine;
    function __construct($redisPool){
        $this->pool = $redisPool;
        $this->_memcachedCoroutine = new MemcachedCoroutine($this->pool);
    }

    function cache($key, $value='', $time_expire=3600){
        if($value === ''){
            $data = yield $this->_memcachedCoroutine->command(['method'=>'get', 'param'=>[$key]]);
        }else{
            if(!is_null($value)) {
                $data = yield $this->_memcachedCoroutine->command(['method' => 'set', 'param' => [$key, $value, $time_expire]]);
            }else{
                $data = yield $this->_memcachedCoroutine->command(['method' => 'delete', 'param' => [$key]]);
            }
        }

        return $data;
    }

}