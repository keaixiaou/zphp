<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/22
 * Time: 下午5:02
 */


namespace ZPHP\Redis;

use ZPHP\Core\Log;
use ZPHP\Coroutine\Redis\RedisCoroutine;

class Redis{
    private $_cmd = ['set', 'get', 'lpop', 'rpop', 'lpush', 'rpush','setex','decr',
        'incr','hset','hget','hmget'];
    private $_pool;
    function __construct($redisPool){
        $this->_pool = $redisPool;
    }

    //redis操作
    public function cache($key, $value='', $expire=0){
        if($value===''){
            $commandData = [ $key];
            $command = 'get';
        }else{
            if(!empty($expire)){
                $command = 'setex';
                $commandData = [ $key, $expire,  $value ];
            }else {
                $command = 'set';
                $commandData = [ $key, $value];
            }
        }

        $data = yield $this->__call($command, $commandData);
        if($value!==''){
            $data = !empty($data)?true:false;
        }
        return $data;
    }

    /**
     * redis命令操作(php暂时不支持在__call里面使用yield)
     * @param $method
     * @param $param
     * @return bool
     */
    public function __call($method,$param){
        if(phpversion()<'7.1' && !in_array($method, $this->_cmd)){
            throw new \Exception("[".$method."]此操作暂时不支持");
        }

        $commandData = $param;
        array_unshift($commandData, $method);
        $redisCoroutine = new RedisCoroutine($this->_pool);
        return $redisCoroutine->command($commandData);
    }


    public function lpush($key, $value){
        return $this->__call('lpush', [$key, $value]);
    }

    public function rpush($key, $value){
        return $this->__call('rpush', [$key, $value]);
    }


    public function lpop($key){
        return $this->__call('lpop', [$key]);
    }

    public function rpop($key){
        return $this->__call('rpop', [$key]);
    }

    public function incr($key){
        return $this->__call('incr', [$key]);
    }

    public function decr($key){
        return $this->__call('decr', [$key]);
    }

    public function hset($key, $field, $value){
        return $this->__call('hset', [$key, $field,$value]);
    }

    public function hget($key, $field){
        return $this->__call('hget', [$key,  $field]);
    }

    public function hmget($key, $fields){
        array_unshift($fields, $key);
        return $this->__call('hmget', $fields);
    }
    public function set($key, $value){
        $setRes = yield $this->__call('set', [$key,  $value]);
        $setRes = $setRes=='OK'?true:false;
        return $setRes;
    }

    public function get($key){
        return $this->__call('get', [$key]);
    }


    public function setex($key, $expire, $value){
        return $this->__call('setex', [$key, $expire, $value]);
    }
}