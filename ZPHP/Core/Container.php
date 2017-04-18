<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/4/15
 * Time: 上午9:20
 */

namespace ZPHP\Core;

abstract class Container{
    /**
     * @var DI
     */
    static private $_di;

    /**
     * 初始化ZPHP框架的容器服务
     */
    static public function init($di)
    {
        self::$_di = $di;
    }
    /**
     * 获取容器组件
     * @param $name - service、model、controller
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    static public function __callStatic($name, $arguments)
    {
        // TODO: Implement __call() method.
        if(empty($arguments)){
            throw new \Exception("组件名不能为空");
        }
        $param = !empty($arguments[1])?$arguments[1]:[];
        return self::make($arguments[0], $name, $param);
    }



    /**
     * get相关的依赖class
     * @param $name
     * @param $type
     */
    static protected function make($name, $type, $arguments=[]){
        $class = self::$_di->get($name, "\\ZPHP\\".$type, $arguments);
        if(empty($class)){
            if(DEBUG){
                throw new \Exception($type.':'.$name.' not found!');
            }

        }
        return $class;
    }
}