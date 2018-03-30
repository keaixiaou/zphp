<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/28
 * Time: 下午4:45
 */


namespace ZPHP\Core;

abstract class Di{


    private static $closureList;

    /**
     * @var $_container Container
     */
    private static $_container;

    public static function init($container){
        self::$_container = $container;
    }

    /**
     * 注入
     * @param $key
     * @param $type
     * @param $objectName
     */
    public static function set($objectName, $params=[]){
        self::$closureList[$objectName] = function()use($objectName, $params){
            return self::$_container->make($objectName, $params);
        };
    }

    /**
     * @param $key
     * @param string $type
     * @return mixed
     */
    public static function get($objectName, $params=[]){
        return self::make($objectName, $params);
    }


    /**
     * @param $key
     * @param string $type
     * @return mixed
     */
    public static function make($objectName, $params=[]){
        if(empty(self::$closureList[$objectName])){
            self::set($objectName, $params);
        }
        return call_user_func(self::$closureList[$objectName]);
    }


    public static function clear($objectName){

    }
}