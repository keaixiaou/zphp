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
    static protected $controllerList = [];
    /**
     * @var DI $_id;
     */
    static protected $_di;


    /**
     * 初始化App的容器服务
     */
    static public function init($di){
        self::$_di = $di;
        self::initClosureList('model');
        self::initClosureList('service');
        self::initClosureList('controller');
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
     * 获取全局model
     * @param $name
     * @return mixed
     */
    static public function getModel($name){
        if(empty(self::$modelList[$name])){
            self::$modelList[$name] = self::get($name, 'model');
        }
        return self::$modelList[$name];
    }


    /**
     * 获取全局service
     * @param $name
     * @return mixed
     */
    static public function getService($name){
        if(empty(self::$serviceList[$name])){
            self::$serviceList[$name] = self::get($name, 'service');
        }
        return self::$serviceList[$name];
    }


    /**
     * 获取全局controller
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    static public function getController($name){
        if(empty(self::$controllerList[$name])){
            self::$controllerList[$name] = self::get($name, 'controller');
        }
        return self::$controllerList[$name];
    }

    /**
     * get相关的依赖class
     * @param $name
     * @param $type
     */
    static public function get($name, $type){
        $class = self::$_di->get($name, $type);
        if(empty($class)){
            throw new \Exception($name.ucfirst($type).' not found!');
        }
        return $class;
    }


}