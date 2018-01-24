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
use ZPHP\Coroutine\Base\IOvector;
use ZPHP\Coroutine\Pool\AsynPool;

class MysqlAsynPool extends AsynPool implements IOvector{

    protected $_asynName = 'mysql';
    protected $_transList = [];
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
    public function command(callable $callback=null,  $data = [])
    {
        $this->checkAndExecute($data, $callback);
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
                $client->isActive = false;
                $this->max_count --;
            });
        }else{
            $client = $tmpClient;
        }
        $set = $this->config;
        $nowConnectNo = $this->max_count;
        unset($set['asyn_max_count']);
        $client->connect($set, function ($client, $result) use($tmpClient,$nowConnectNo, $data) {
            try {
                if($result===false) {
                    $this->max_count --;
                    $exceptionMsg = "[mysql连接失败]".$client->connect_error;
                    throw new \Exception($exceptionMsg);
                } else {
                    $client->isActive = true;
                    $client->isAffair = false;
                    $client->client_id = $tmpClient ? $tmpClient->client_id : $nowConnectNo;
                    $this->commands->enqueue($data);
                    $this->pushToPool($client);
                }
            }catch(\Exception $e){
                if(!empty($data)) {
                    $data['result']['exception'] = $e;
                    $this->distribute($data);
                }
            }
        });
    }


    /**
     * 执行mysql命令
     * @param $data
     */
    public function execute($data)
    {
        $needCreateClient = true;
        if(!empty($data['trans_id']) && !empty($this->_transList[$data['trans_id']])){
            $client = $this->_transList[$data['trans_id']];
        }else{
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
            if(!empty($data['trans_id'])) {
                $this->_transList[$data['trans_id']] = $client;
            }
        }

        $sql = $data['sql'];
        $queryCallback = function ($client, $result) use ($data) {
            try {
                $sql = strtolower($data['sql']);
                if ($result === false) {
                    if(!empty($data['trans_id'])){
                        if($sql==='rollback'||$sql==='commit') {
                            unset($this->_transList[$data['trans_id']]);
                            $this->max_count--;
                        }
                    }else{
                        $this->max_count--;
                    }
                    throw new \Exception("[mysql客户端操作失败]:" . $client->error . "[sql]:" . $data['sql']);
                } else {

                    if($sql==='begin'){
                        $data['result']['result'] = $data['trans_id'];
                    }else{
                        $data['result']['result'] = $result;
                    }
                    $data['result']['client_id'] = $client->client_id;
                    $data['result']['affected_rows'] = $client->affected_rows;
                    $data['result']['insert_id'] = $client->insert_id;
//                    unset($data['sql']);
                    //不是绑定的连接就回归连接
                    if(empty($data['trans_id'])) {
                        $this->pushToPool($client);
                    }else{
                        if($sql==='rollback' || $sql==='commit'){
                            $this->freeTransConnect($data);
                        }
                    }
                    $this->distribute($data);
                }
            }catch(\Exception $e){
                $data['result']['exception'] = $e;
                $this->distribute($data);
            }
        };
        $res = $client->query($sql, $queryCallback);
        if(empty($res)){
            $data['result']['exception'] = "执行sql[$sql]失败";
            $this->distribute($data);
        }
    }


    /**
     * 释放事务连接,回归到连接池
     * @param $data
     */
    protected function freeTransConnect($data){
        if(!empty($data['trans_id'])){
            $client = $this->_transList[$data['trans_id']];
            $this->pushToPool($client);
            unset($this->_transList[$data['trans_id']]);
        }

    }

}