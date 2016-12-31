<?php
/**
 * redis 异步客户端连接池
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-22
 * Time: 上午10:19
 */

namespace ZPHP\Coroutine\Redis;

use ZPHP\Core\Config;
use ZPHP\Core\Log;
use ZPHP\Coroutine\Pool\AsynPool;

class RedisAsynPool extends AsynPool
{
    const AsynName = 'redis';

    protected  $operator = [
        'password'  =>  ['op'=>'auth','next'=>'select'],
        'select'    =>  ['op'=>'select','next'=>''],
    ];

    protected $cmd = ['set', 'get', 'lpop', 'lpush', 'rpush','setex','decr', 'incr',
        'hset','hget'];
    /**
     * 连接
     * @var array
     */
    public $connect;

    public function __construct($connect = null)
    {
        parent::__construct();
        $this->connect = $connect;
    }


    /**
     * redis的cache方法
     * @param $name
     * @param $arguments
     */
    public function command($callback, $data)
    {
        $data['token'] = $this->addTokenCallback($callback);
        call_user_func([$this, 'execute'], $data);
    }


    /**
     * 执行redis命令
     * @param $data
     */
    public function execute($data)
    {
        if ($this->pool->isEmpty()) {//代表目前没有可用的连接
            $this->prepareOne($data);
            $this->commands->push($data);
        } else {
            $client = $this->pool->dequeue();
            $callback = function ($client, $result) use ($data) {
                try {
                    if ($result === false) {
                        throw new \Exception("[redis客户端操作失败]");
                    } else {
                        $data['result'] = $result;
                        $this->pushToPool($client);
                    }
                }catch(\Exception $e){
                    Log::write($e->getMessage());
                    $data['result']['exception'] = $e->getMessage();
                }
                //给worker发消息
                call_user_func([$this, 'distribute'], $data);
            };
            try{
                $execute = $data['execute'];
                $command = array_shift($execute);
                if(!in_array($command, $this->cmd)){
                    throw new \Exception("[".$command."]此操作暂时不支持");
                }
                $execute[] = $callback;
                $res = call_user_func_array([$client, $command], $execute);
                if(empty($res)){
                    throw new \Exception("redis客户端操作失败");
                }
            }catch(\Exception $e){
                $data['result']['exception'] = $e->getMessage();
                call_user_func([$this, 'distribute'], $data);
            }
        }
    }

    /**
     * 准备一个redis
     * param data['token']用作异常捕获向上的索引
     */
    public function prepareOne($data)
    {
        if ($this->max_count > $this->config['asyn_max_count']) {
            return;
        }
        $this->max_count++;
        $nowConnectNo = $this->max_count;

        $client = new \swoole_redis;
        $client->on('Close', function($client){
            call_user_func([$this, 'clearPool']);
        });
        if ($this->connect == null) {
            $this->connect = [$this->config['ip'], $this->config['port']];
        }
        $client->connect($this->connect[0], $this->connect[1],
            function (\swoole_redis $client, $result) use ($nowConnectNo, $data) {
                try {
                    if (!$result) {
                        $this->max_count--;
                        throw new \Exception('[redis连接失败]' . $client->errMsg);
                    }
                    call_user_func([$this, 'initRedis'], $client, 'password', $nowConnectNo, $data);
                } catch (\Exception $e) {
                    Log::write($e->getMessage());
                    if(!empty($data)){
                        $data['result']['exception'] = $e->getMessage();
                        call_user_func([$this, 'distribute'], $data);
                    }

                }
        });
    }


    /**
     * redis客户端的初始化操作
     * @param $client redis客户端
     * @param string $now 当前步骤
     * @param int $nowConnectNo 当前客户端编号
     * @param array $data['token']异常回调的索引
     */
    public function initRedis($client, $now, $nowConnectNo,$data){

        if(!empty($this->operator[$now]['op'])){
            if(!empty($this->config[$now])){
                $operat = $this->operator[$now]['op'];
                $client->$operat($this->config[$now], function ($client, $result)use($now, $nowConnectNo,$data) {
                    try {
                        if (!$result) {
                            $errMsg = $client->errMsg;
                            $this->max_count--;
                            unset($client);
                            throw new \Exception('[redis连接失败]'.$errMsg);
                        }
                        call_user_func([$this, 'initRedis'], $client, $this->operator[$now]['next'], $nowConnectNo, $data);
                    }catch(\Exception $e){
                        Log::write($e->getMessage());
                        if(!empty($data)) {
                            $data['result']['exception'] = $e->getMessage();
                            call_user_func([$this, 'distribute'], $data);
                        }
                    }
                });
            }else{
                $this->initRedis($client, $this->operator[$now]['next'],$nowConnectNo,$data);
            }
        }else{
            $this->pushToPool($client);
        }


    }
    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName;
    }

}