<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/28
 * Time: 下午4:45
 */


namespace ZPHP\Core;

class DI{
    static protected $closureList;

    /**
     * 注入
     * @param $key
     * @param $type
     * @param $objectName
     */
    static public function set($key, $type, $objectName){
        self::$closureList[$type][$key] = function()use($objectName){
            return Factory::getInstance($objectName);
        };
    }

    /**
     * @param $key
     * @param string $type
     * @return mixed
     */
    static public function get($key, $type='model'){
        if(empty(self::$closureList[$type][$key])){
            if($type=='controller'){
                $objectName = $type."s\\Home\\".ucfirst($key);
            }else{
                $objectName = $type."\\".$key;
            }

            self::set($key, $type, $objectName);
        }
        return call_user_func(self::$closureList[$type][$key]);
    }
}