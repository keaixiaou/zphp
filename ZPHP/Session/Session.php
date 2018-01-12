<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/14
 * Time: 11:11
 */

namespace ZPHP\Session;
use ZPHP\Core\Config;


class Session
{
    public static $_sessionKey = 'SESSID';


    public static function init(){
        self::$_sessionKey = Config::get('project_name').self::$_sessionKey;
    }
    /**
     * 获取session
     * @param $request
     * @param $response
     * @return array|mixed
     * @throws \Exception
     */
    public static function get($sid){
        $session = [];
        $config = Config::get('session');
        if(!empty($sid)){
            $sid = $config['name'].$sid;
            $sessionType = $config['adapter'];
            $handler = Factory::getInstance($sessionType, $config);
            $data = yield $handler->read($sid);
            if(!empty($data)) {
                $session = unserialize($data);
            }
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
    public static function set($session, $sid){
        $config = Config::get('session');
        $sessionType = $config['adapter'];
        $handler = Factory::getInstance($sessionType, $config);
        $sid = $config['name'].$sid;
        $res = yield $handler->write($sid, serialize($session));
        return $res;
    }
}