<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/14
 * Time: 11:11
 */

namespace ZPHP\Session;
use ZPHP\Core\Config as ZConfig;
use ZPHP\Core\Rand;


class Session
{
    private static $_sessionKey = 'PHPSESSID';

    /**
     * 获取session
     * @param $request
     * @param $response
     * @return array|mixed
     * @throws \Exception
     */
    public static function get($request, $response){
        $sid = $request->cookie[self::$_sessionKey];
        $session = [];
        $config = ZConfig::get('session');
        if(!empty($sid)){
            $sessionType = $config['adapter'];
            $handler = Factory::getInstance($sessionType, $config);
            $data = $handler->read($sid);
            if(!empty($data)) {
                $session = unserialize($data);
            }
        }else{
            $res = $response->cookie(self::$_sessionKey, Rand::string(8),
                time()+$config['cache_expire']);
        }
        return $session;
    }

    /**
     * @param $session
     * @param $request
     * @param $response
     * @return mixed
     * @throws \Exception
     */
    public static function set($session, $request, $response){
        $sid = $request->cookie[self::$_sessionKey];
        $config = ZConfig::get('session');
        if(empty($sid)){
            $sid = Rand::string(8);
            $response->cookie(self::$_sessionKey, $sid, time()+$config['cache_expire']);
        }
        $sessionType = $config['adapter'];
        $handler = Factory::getInstance($sessionType, $config);
        $res = $handler->write($sid, serialize($session));
        return $res;
    }
}