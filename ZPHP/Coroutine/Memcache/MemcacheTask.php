<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/6
 * Time: 下午3:14
 */

namespace ZPHP\Coroutine\Memcache;


class MemcacheTask{
    public $taskId;
    protected $manager;
    protected $config;
    function __construct($param = [])
    {
        $this->taskId = $param['taskId'];
        $this->config = $param['config'];

    }

    protected function checkManager(){
        if(empty($this->manager)) {
            $this->manager = new \Memcache();
            $this->manager->connect($this->config['host'], $this->config['port']);
        }
    }

    public function get($key){
        $this->checkManager();
        $res = $this->manager->get($key);
        return $res;
    }


    public function set($key, $value, $flag, $time_expire=3600){
        $this->checkManager();
        return $this->manager->set($key, $value, $flag, $time_expire);
    }

    public function delete($key){
        $this->checkManager();
        return $this->manager->delete($key);
    }
}