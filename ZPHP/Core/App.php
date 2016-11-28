<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/28
 * Time: 下午2:03
 */


namespace ZPHP\Core;

abstract class App{

    static protected $modelList = [];
    static protected $serviceList = [];
    static protected $_di;


    /**
     * 初始化App的容器服务
     */
    static public function init($di){
        self::$_di = $di;
        self::initClosureList('model');
        self::initClosureList('service');
    }


    /**
     * 注入配置里的服务
     * @param $type
     * @throws \Exception
     */
    static public function initClosureList($type){
        $modelConfig = Config::get($type);
        foreach($modelConfig as $key => $value) {
            self::$_di->set($key, $type, $value);
        }
    }


    /**
     * @param $name
     * @return mixed
     */
    static public function getModel($name){
        if(empty(self::$modelList[$name])){
            self::$modelList[$name] = self::$_di->get($name, 'model');
        }
        return self::$modelList[$name];
    }


    /**
     * @param $name
     * @return mixed
     */
    static public function getService($name){
        if(empty(self::$serviceList[$name])){
            self::$serviceList[$name] = self::$_di->get($name, 'service');
        }
        return self::$serviceList[$name];
    }



}