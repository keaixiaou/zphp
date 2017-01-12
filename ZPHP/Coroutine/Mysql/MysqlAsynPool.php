<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/14
 * Time: 下午2:58
 */

namespace ZPHP\Coroutine\Mysql;

use ZPHP\Core\Config;
use ZPHP\Core\Log;
use ZPHP\Coroutine\Pool\AsynPool;

class MysqlAsynPool extends AsynPool{

    const AsynName = 'mysql';

    /**
     * @var array
     */
    public $bind_pool;

    public function __construct()
    {
        parent::__construct();
    }


    /**
     * 执行一个sql语句
     * @param $callback
     * @param $bind_id 绑定的连接id，用于事务
     * @param $sql
     */
    public function query(callable $callback,  $sql = null)
    {
        $data = [
            'sql' => $sql
        ];
        $data['token'] = $this->addTokenCallback($callback);
        call_user_func([$this, 'execute'], $data);
    }

    /**
     * 执行mysql命令
     * @param $data
     */
    public function execute($data)
    {
        if ($this->pool->isEmpty()) {//代表目前没有可用的连接
            $this->prepareOne($data);
            $this->commands->enqueue($data);
            return;
        } else {
            $client = $this->pool->dequeue();
        }

        $sql = $data['sql'];
        $res = $client->query($sql, function ($client, $result) use ($data) {
            Log::write('res:'.print_r($result, true));
            try {
                if ($result === false) {
                    if ($client->errno == 2006 || $client->errno == 2013) {//断线重连
                        $this->reconnect($data, $client);
                        unset($client);
                        $this->commands->unshift($data);
                    } else {
                        throw new \Exception("[mysql客户端操作失败]:" . $client->error . "[sql]:" . $data['sql']);
                    }
                } else {
                    $data['result']['client_id'] = $client->client_id;
                    $data['result']['result'] = $result;
                    $data['result']['affected_rows'] = $client->affected_rows;
                    $data['result']['insert_id'] = $client->insert_id;
                    unset($data['sql']);
                    //不是绑定的连接就回归连接
                    $this->pushToPool($client);

                    //给worker发消息
                    call_user_func([$this, 'distribute'], $data);

                }
            }catch(\Exception $e){
                $data['result']['exception'] = $e->getMessage();
                call_user_func([$this, 'distribute'], $data);
            }
        });
        Log::write('res:'.print_r($res, true));
        if(empty($res)){
            $data['result']['exception'] = "执行sql[$sql]失败";
            call_user_func([$this, 'distribute'], $data);
        }
    }

    /**
     * 重连或者连接
     * @param array $data['token'] 异常回调的索引
     * @param null $client
     */
    public function reconnect($data, $tmpClient = null)
    {
        if ($tmpClient == null) {
            $client = new \swoole_mysql();
            $client->on('Close', function($client){
                call_user_func([$this, 'clearPool']);
            });
        }else{
            $client = $tmpClient;
        }
        $set = $this->config;
        $nowConnectNo = $this->max_count;
        unset($set['asyn_max_count']);
        $client->connect($set, function ($client, $result) use($tmpClient,$nowConnectNo, $data) {
            try {
                if (!$result) {
                    $this->max_count --;
                    $exceptionMsg = "[mysql连接失败]".$client->connect_error;
                    throw new \Exception($exceptionMsg);
                } else {
                    $client->isAffair = false;
                    $client->client_id = $tmpClient ? $tmpClient->client_id : $nowConnectNo;
                    $this->pushToPool($client);
                }
            }catch(\Exception $e){
                Log::write('$max_count:'.$this->max_count);
                Log::write($e->getMessage());
                if(!empty($data)) {
                    $data['result']['exception'] = $e->getMessage();
                    call_user_func([$this, 'distribute'], $data);
                }
            }
        });
    }

    /**
     * 准备一个mysql
     */
    public function prepareOne($data)
    {
        if ($this->max_count >= $this->config['asyn_max_count']) {
            return;
        }

        $this->max_count ++;
        $this->reconnect($data);
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName;
    }





}