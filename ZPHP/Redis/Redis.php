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
    protected $pool;
    protected $_redisCoroutine;
    function __construct($redisPool){
        $this->pool = $redisPool;
        $this->_redisCoroutine = new RedisCoroutine($this->pool);
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
        $commandData = $param;
        array_unshift($commandData, $method);
        $data = yield $this->_redisCoroutine->command(['execute'=>$commandData]);
        return $data;
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


}