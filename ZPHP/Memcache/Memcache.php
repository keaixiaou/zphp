<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/6
 * Time: 下午3:16
 */

namespace ZPHP\Memcache;

use ZPHP\Coroutine\Memcache\MemcacheCoroutine;

class Memcache{
    protected $pool;
    protected $_memcacheCoroutine;
    function __construct($redisPool){
        $this->pool = $redisPool;
        $this->_memcacheCoroutine = new MemcacheCoroutine($this->pool);
    }

    function cache($key, $value='', $time_expire=3600){
        if($value === ''){
            $data = yield $this->_memcacheCoroutine->command(['method'=>'get', 'param'=>[$key]]);
        }else{
            if(!is_null($value)) {
                $data = yield $this->_memcacheCoroutine->command(['method' => 'set', 'param' => [$key, $value, 0, $time_expire]]);
            }else{
                $data = yield $this->_memcacheCoroutine->command(['method' => 'delete', 'param' => [$key]]);
            }
        }

        return $data;
    }

}