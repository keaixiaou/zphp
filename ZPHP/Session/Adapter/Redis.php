<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Session\Adapter;

use ZPHP\Core\Db;

class Redis
{
    private $redis;
    private $gcTime = 1800;
    private $config;

    public function __construct($config)
    {
        if (empty($this->redis)) {
            $this->redis = Db::sessionRedis();
            if (!empty($config['cache_expire'])) {
                $this->gcTime = $config['cache_expire'] ;
            }
            $this->config = $config;
        }
    }

    public function open($path, $sid)
    {
        return !empty($this->redis);
    }

    public function close()
    {
        return true;
    }

    public function gc($time)
    {
        return true;
    }

    public function read($sid)
    {
        if(!empty($this->config['sid_prefix'])) {
            $sid = str_replace($this->config['sid_prefix'], '', $sid);
        }
        $data = yield $this->redis->cache($sid);
        if (!empty($data)) {
            yield $this->redis->cache($sid, $data, $this->gcTime);
        }
        return $data;
    }

    public function write($sid, $data)
    {
        if(empty($data)) {
            return true;
        }
        $res = yield $this->redis->cache($sid, $data, $this->gcTime);
        return $res;
    }

    public function destroy($sid)
    {
        if(!empty($this->config['sid_prefix'])) {
            $sid = str_replace($this->config['sid_prefix'], '', $sid);
        }
        return $this->redis->delete($sid);
    }
}
