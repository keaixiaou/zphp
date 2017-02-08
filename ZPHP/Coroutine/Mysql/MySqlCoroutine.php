<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/14
 * Time: 下午3:47
 */


namespace ZPHP\Coroutine\Mysql;

use ZPHP\Core\Log;
use ZPHP\Coroutine\Base\CoroutineResult;
use ZPHP\Coroutine\Base\ICoroutineBase;

class MySqlCoroutine implements ICoroutineBase{
    /**
     * @var MysqlAsynPool
     */
    public $_mysqlAsynPool;
    public $bind_id;
    public $sql;
    public $result;

    public function __construct($mysqlAsynPool)
    {
        $this->result = CoroutineResult::getInstance();
        $this->_mysqlAsynPool = $mysqlAsynPool;
    }

    public function query($sql){
        $this->sql = $sql;
        $data = yield $this;
        return $data;
    }


    /**
     * @param $callback
     * @throws \Exception
     */
    public function send(callable $callback)
    {
        $this->_mysqlAsynPool->command($callback, $this->sql);
    }

    public function getResult()
    {
        return $this->result;
    }
}