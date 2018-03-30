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
use ZPHP\Coroutine\Base\IOvector;
use ZPHP\Coroutine\Pool\AsynPool;

class RedisAsynPool extends AsynPool implements IOvector
{
    protected $_asynName = 'redis';

    protected  $operator = [
        'password'  =>  ['op'=>'auth','next'=>'select'],
        'select'    =>  ['op'=>'select','next'=>''],
    ];

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
    public function command(callable $callback=null, $data)
    {
        $this->checkAndExecute(["execute"=>$data], $callback);
    }


    /**
     * 执行redis命令
     * @param $data
     */
    public function execute($data)
    {
        $needCreateClient = true;
        $client = null;
        //代表目前没有可用的连接
        while(!$this->pool->isEmpty()){
            $client = $this->pool->dequeue();
            if($client->isActive===true){
                $needCreateClient = false;
                break;
            }else{
                unset($client);
            }
        }
        if($needCreateClient){
            $this->prepareOne($data);
            return;
        }
        $callback = function ($client, $result) use ($data) {
            try {
                if ($result === false) {
                    $this->max_count--;
                    throw new \Exception("[redis客户端操作失败]");
                } else {
                    $data['result'] = $result;
                    $this->pushToPool($client);
                }
            }catch(\Exception $e){
                $data['result']['exception'] = $e;
            }
            //给worker发消息
            $this->distribute($data);
        };
        try{
            $execute = $data['execute'];
            $command = array_shift($execute);

            $execute[] = $callback;
            $res = call_user_func_array([$client, $command], $execute);
            if(empty($res)){
                throw new \Exception("redis客户端操作失败");
            }
        }catch(\Exception $e){
            $data['result']['exception'] = $e;
            $this->distribute($data);
        }
    }

    /**
     * @param $data
     */
    public function reconnect($data){
        $nowConnectNo = $this->max_count;

        $client = new \swoole_redis;
        $client->on('Close', function($client){
            $client->isActive = false;
            $this->max_count --;
        });
        $client->isActive = true;
        if ($this->connect == null) {
            $this->connect = [$this->config['ip'], $this->config['port']];
        }
        $connectCallback = function (\swoole_redis $client, $result) use ($nowConnectNo, $data) {
            try {
                if (!$result) {
                    $this->max_count--;
                    throw new \Exception('[redis reconnect连接失败]' . $client->errMsg);
                }
                $this->initRedis($client, 'password', $nowConnectNo, $data);
            } catch (\Exception $e) {
                if(!empty($data)){
                    $data['result']['exception'] = $e;
                    $this->distribute($data);
                }

            }
        };
        $client->connect($this->connect[0], $this->connect[1], $connectCallback);
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
                        throw new \Exception('[redis initRedis连接失败]'.$errMsg);
                    }
                    $this->initRedis( $client, $this->operator[$now]['next'], $nowConnectNo, $data);
                }catch(\Exception $e){
                    $data['result']['exception'] = $e;
                    $this->distribute($data);
                }
            });
            }else{
                $this->initRedis($client, $this->operator[$now]['next'],$nowConnectNo,$data);
            }
        }else{
            $this->commands->enqueue($data);
            $this->pushToPool($client);
        }


    }

}