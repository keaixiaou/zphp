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
    public function cache($key, $value='', $expire=3600){
        $commandData = ['key'=>$key, 'value'=>$value,'expire'=>$expire];
        if($value===''){
            $commandData['command'] = 'get';
        }else{
            $commandData['command'] = 'set';
        }
        $data = yield $this->_redisCoroutine->command($commandData);
        if($value!==''){
            $data = !empty($data)?true:false;
        }
        return $data;
    }

    /**
     * redis命令操作
     * @param $method
     * @param $param
     * @return bool
     */
    public function __call($method,$param){
        $commandData = ['key'=>$param[0]];
        $commandData['value'] = !empty($param[1])? $param[1]:'';
        $commandData['expire'] = !empty($param[2])? $param[2]:3600;
        $commandData['command'] = $method;
        $data = yield $this->_redisCoroutine->command($commandData);
        if($commandData['value']!==''){
            $data = !empty($data)?true:false;
        }
        return $data;
    }


    public function lpush($key, $value){
        return $this->__call('lpush', [$key, $value]);
    }

    public function lpop($key){
        return $this->__call('lpop', [$key]);
    }

}