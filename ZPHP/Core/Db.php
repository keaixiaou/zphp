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
//use ZPHP\Db\Mongo;
use ZPHP\Memcached\Memcached;
use ZPHP\Model\Model;
use ZPHP\Coroutine\Mysql\MysqlAsynPool;
use ZPHP\Mongo\Mongo;
use ZPHP\Redis\Redis;

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
     * @var MemcacheAsynPool
     */
    public $memcachedPool;
    /**
     * @var RedisAsynPool
     */
    public $sessionRedisPool;

    public static $server;
    public static $instance;
    protected static $db;
    protected static $_tables;
    protected static $_redis;
    protected static $_mongo;
    protected static $_memcached;
    protected static $_sessionRedis;
    protected static $_collection;
    private static $lastSql;
    private static $workId;

    private function __construct(){
        self::$instance = & $this;
    }

    /**
     * @return Db
     */
    public static function getInstance(){
        if(!isset(self::$instance)){
            self::$instance = new Db();
        }
        return self::$instance;
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
        self::initMysqlPool($workerId, Config::getField('database','master'));
        self::initRedisPool($workerId, Config::get('redis'));
        self::initMongoPool($workerId, self::$server, Config::get('mongo'));
        self::initSessionRedisPool($workerId, Config::get('session'));
        self::initMemcachedPool($workerId, self::$server, Config::get('memcached'));
    }
    /**
     * @param $workId
     * 初始化mysql连接池
     */
    static public function initMysqlPool($workId, $config){
        if(!empty($config)) {
            if (empty(self::$instance->mysqlPool)) {
                self::$workId = $workId;
                self::$instance->mysqlPool = new MysqlAsynPool();
                self::$instance->mysqlPool->initWorker($workId, $config);
            }
        }
    }


    /**
     * 释放mysql连接池
     */
    static public function freeMysqlPool(){
        if(isset(self::$instance->mysqlPool)) {
            self::$instance->mysqlPool->free();
            unset(self::$instance->mysqlPool);
        }
    }

    /**
     * @param string $tableName
     * @param string $db_key
     * @return Model
     */
    public static function table($tableName='', $db_key = 'master'){
        if(!isset(self::$_tables[$tableName])){
            self::$_tables[$tableName] = new Model(self::$instance->mysqlPool);
            self::$_tables[$tableName]->table = $tableName;
        }
        return self::$_tables[$tableName];
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
            self::$instance->mongoPool->initTaskWorker($workId, $config, $server);
        }
    }


    public static function initMemcachedPool($workId, $server, $config){
        if(empty(self::$instance->memcachedPool)){
            self::$instance->memcachedPool = new MemcachedAsynPool();
            self::$instance->memcachedPool->initTaskWorker($workId, $config, $server);
        }
    }
    /**
     * @param $collection
     * @return Mongo
     */
    static public function collection($collection){
        if(!isset(self::$_mongo[$collection])){
            self::$_mongo[$collection] = new Mongo($collection,self::$instance->mongoPool);
        }
        return self::$_mongo[$collection];
    }

    /**
     * free redis pool
     */
    static public function freeRedisPool(){
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