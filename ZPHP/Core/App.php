<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/28
 * Time: 下午2:03
 */


namespace ZPHP\Core;

use Main\Main;
use ZPHP\Common\Dir;
use ZPHP\ZPHP;

abstract class App{
    static protected $compenontType = ['controller','service','model','middleware'];
    /**
     * @var DI $_id;
     */
    static protected $_di;


    /**
     * 初始化App的容器服务
     */
    static public function init()
    {
        foreach (self::$compenontType as $type) {
            self::initConfigList($type);
            self::initDefaultList($type);
        }
    }


    /**
     * 默认的初始化
     * @param $type
     * @throws \Exception
     */
    public static function initDefaultList($type){
        $dir = ZPHP::getAppPath().DS.$type;
        if(is_dir($dir)) {
            $classList = Dir::getClass($dir, '/.php$/');
            foreach($classList as $key => $value){
                $value = self::getComponentName($value);
                Di::set($type.'\\'.$value);
            }
        }
    }

    /**
     * 配置文件的服务初始化
     * @param $type
     * @throws \Exception
     */
    public static function initConfigList($type){
        $modelConfig = Config::get($type);
        if(!empty($modelConfig)) {
            foreach ($modelConfig as $key => $value) {
                $key = self::getComponentName($key);
                Di::make($type.'\\'.$value);
            }
        }
    }


    /**
     * 获取容器组件
     * @param $name - service、model、controller
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __call() method.
        if(empty($arguments)){
            throw new \Exception("组件名不能为空");
        }
        $key = self::getComponentName($arguments[0]);
        $argu = !empty($arguments[1])?$arguments[1]:[];
        return self::get($key.'\\'.$name, $argu);
    }


    static protected function getComponentName($name){
//        if(strpos($name, '\\')){
//            $keyArray = explode('\\', $name);
//            foreach($keyArray as $k=>$v){
//                $keyArray[$k] = ucfirst($v);
//            }
//            $key = implode('\\', $keyArray);
//        }else{
//            $key = ucfirst($name);
//        }
        return $name;
    }

    static public function make($objectName, $arguments=[]){
        return Di::make($objectName, $arguments);
    }

    /**
     * get相关的依赖class
     * @param $name
     * @param $type
     */
    static public function get($name, $argu=[]){
        $class = Di::get($name, $argu);
        if(empty($class)){
            if(DEBUG){
                throw new \Exception($name.' not found!');
            }
        }
        return $class;
    }


}