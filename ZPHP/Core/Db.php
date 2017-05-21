<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/7/16
 * Time: 下午2:52
 */


namespace ZPHP\Core;

use ZPHP\Coroutine\Memcached\MemcachedAsynPool;
use ZPHP\Coroutine\Mongo\MongoAsynPool;
use ZPHP\Coroutine\Redis\RedisAsynPool;
use ZPHP\Coroutine\Task\TaskAsynPool;
use ZPHP\Memcached\Memcached;
use ZPHP\Model\Model;
use ZPHP\Coroutine\Mysql\MysqlAsynPool;
use ZPHP\Mongo\Mongo;
use ZPHP\Redis\Redis;
use ZPHP\Task\Task;

class Db {
    /**
     * @var MysqlAsynPool
     */
    public $mysqlPool;

    /**
     * @var RedisAsynPool
     */
    public $redisPool;


    /**
     * @var MongoAsynPool
     */
    public $mongoPool;
    /**
     * @var MemcachedAsynPool
     */
    public $memcachedPool;
    /**
     * @var RedisAsynPool
     */
    public $sessionRedisPool;

    /**
     * @var TaskAsynPool $taskPool
     */
    public $taskPool;

    private static $server;
    private static $instance=null;
    private static $db;
    private static $_tables;
    private static $_redis;
    private static $_mongo;
    private static $_memcached;
    private static $_sessionRedis;
    private static $_task;
    private static $_collection;
    private static $lastSql;
    private static $workId;
    private static $_swooleModule;

    private function __construct(){
    }

    /**
     * @return Db
     */
    public static function getInstance(){
        if(empty(self::$instance)){
            self::$instance = new Db();
        }
        return self::$instance;
    }

    public static function getServer(){
        return self::$server;
    }

    /**
     * @return workId
     */
    public static function getWorkId(){
        return self::$workId;
    }


    /**
     * DB类初始化
     * @param $server
     * @param $workerId
     * @throws \Exception
     */
    static public function init($server, $workerId){
        self::getInstance();
        self::$server = $server;
        self::initMysqlPool($workerId, Config::get('mysql'));
        self::initRedisPool($workerId, Config::get('redis'));
        self::initMongoPool($workerId, self::$server, Config::get('mongo'));
        self::initSessionRedisPool($workerId, Config::get('session'));
        self::initMemcachedPool($workerId, self::$server, Config::get('memcached'));
        $taskConfig = ['asyn_max_count'=>Config::getField('socket', 'single_task_worker_num')];
        self::initTaskPool($workerId, self::$server, $taskConfig);
        self::initSwooleModule(Config::get('swoole_module'));
    }

    /**
     * @param $config
     */
    public static function initSwooleModule($config){
        if(!empty($config)) {
            foreach ($config as $key => $value) {
                self::$_swooleModule[$key] = \swoole_load_module($value);
            }
        }
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function getSwooleModule($name){
        return self::$_swooleModule[$name];
    }


    /**
     * @param $workId
     * 初始化mysql连接池
     */
    public static function initMysqlPool($workId, $config){
        if(!empty($config)) {
            foreach ($config as $dbKey =>$DBconfig){
                if (empty(self::$instance->mysqlPool[$dbKey])) {
                    self::$workId = $workId;
                    self::$instance->mysqlPool[$dbKey] = new MysqlAsynPool();
                    self::$instance->mysqlPool[$dbKey]->initWorker($workId, $DBconfig);
                }
            }
        }
    }

    /**
     * @param $workId
     */
    public static function initRedisPool($workId, $config){
        if(!empty($config)) {
            if (empty(self::$instance->redisPool)) {
                self::$instance->redisPool = new RedisAsynPool();
                self::$instance->redisPool->initWorker($workId, $config);
            }
        }
    }


    /**
     * 初始化
     * @param $workId
     * @throws \Exception
     */
    public static function initSessionRedisPool($workId, $config){
        if($config['enable'] && strtolower($config['adapter'])=='redis') {
            if (empty(self::$instance->sessionRedisPool)) {
                self::$instance->sessionRedisPool = new RedisAsynPool();
                $sRedisConf = $config['redis'];
                self::$instance->sessionRedisPool->initWorker($workId, $sRedisConf);
            }
        }
    }


    /**
     * init mongoPool
     * @param $workId
     */
    public static function initMongoPool($workId, $server, $config){
        if(empty(self::$instance->mongoPool)){
            self::$instance->mongoPool = new MongoAsynPool();
            self::$instance->mongoPool->initTaskWorker($workId, $server, $config);
        }
    }

    /**
     * 初始化memcached连接池
     * @param $workId
     * @param $server
     * @param $config
     */
    public static function initMemcachedPool($workId, $server, $config){
        if(empty(self::$instance->memcachedPool)){
            self::$instance->memcachedPool = new MemcachedAsynPool();
            self::$instance->memcachedPool->initTaskWorker($workId, $server, $config);
        }
    }

    /**
     * 普通task连接池
     * @param $workId
     * @param $server
     * @param $config
     */
    public static function initTaskPool($workId, $server, $config){
        if(empty(self::$instance->taskPool)){
            self::$instance->taskPool = new TaskAsynPool();
            self::$instance->taskPool->initTaskWorker($workId, $server, $config);
        }
    }


    /**
     * @param string $tableName
     * @param string $db_key
     * @return Model
     */
    public static function table($tableName=''){
        if(!isset(self::$_tables[$tableName])){
            if(strpos($tableName , '#') !== false){
                list($DbKey, $_tableName) = explode('#', $tableName);
                if(empty($DbKey)) $DbKey = 'default';
            }else{
                $DbKey = 'default';
                $_tableName = $tableName;
            }
            self::$_tables[$tableName] = new Model($_tableName, self::$instance->mysqlPool[$DbKey]);
        }
        return self::$_tables[$tableName];
    }


    /**
     * @param $collection
     * @return Mongo
     */
    public static function collection($collection=''){
        if(!isset(self::$_mongo[$collection])){
            self::$_mongo[$collection] = new Mongo($collection,self::$instance->mongoPool);
        }
        return self::$_mongo[$collection];
    }



    /**
     * @return Redis
     */
    public static function redis(){
        if(!isset(self::$_redis)){
            self::$_redis = new Redis(self::$instance->redisPool);
        }
        return self::$_redis;
    }


    public static function memcached(){
        if(!isset(self::$_memcached)){
            self::$_memcached = new Memcached(self::$instance->memcachedPool);
        }
        return self::$_memcached;
    }

    public static function task(){
        if(!isset(self::$_task)){
            self::$_task = new Task(self::$instance->taskPool);
        }
        return self::$_task;
    }

    /**
     * 用于session的redis连接池
     * @return Redis
     */
    public static function sessionRedis(){
        if(!isset(self::$_sessionRedis)){
            self::$_sessionRedis = new Redis(self::$instance->sessionRedisPool);
        }
        return self::$_sessionRedis;
    }


    /**
     * 释放mysql连接池
     */
    public static function freeMysqlPool(){
        if(is_array(self::$instance->mysqlPool)){
            foreach (self::$instance->mysqlPool as $DbKey => $DBconfig){
                if(isset(self::$instance->mysqlPool[$DbKey])) {
                    self::$instance->mysqlPool[$DbKey]->free();
                    unset(self::$instance->mysqlPool[$DbKey]);
                }
            }
        }
    }

    /**
     * free redis pool
     */
    public static function freeRedisPool(){
        if(isset(self::$instance->redisPool)) {
            self::$instance->redisPool->free();
            unset(self::$instance->redisPool);
        }
        if(isset(self::$instance->sessionRedisPool)) {
            self::$instance->sessionRedisPool->free();
            unset(self::$instance->sessionRedisPool);
        }
    }

    /**
     * pdo 查询获取pdo(同步)
     * @param string $db_key
     * @return mixed
     * @throws \Exception
     */
    public function getDb($db_key= 'master'){
        if(!isset(self::$db[$db_key])){
            $config = Config::getField('db', $db_key);
            if($config['type']=='pdo'){
                if(empty($config['persistent'])) {
                    self::$db[$db_key] = new \PDO($config['dsn'], $config['user'], $config['password']);
                }else{
                    self::$db[$db_key] = new \PDO($config['dsn'], $config['user'], $config['password'],
                        array(\PDO::ATTR_PERSISTENT => true));
                }
                if(!empty($config['charset'])){
                    self::$db[$db_key]->query('set names ' . $config['charset']);
                }
                self::$db[$db_key]->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

            }
        }
        return self::$db[$db_key];
    }

    public static function setSql($sql){
        self::$lastSql = $sql;
    }

    public static function getLastSql(){
        return self::$lastSql;
    }




}