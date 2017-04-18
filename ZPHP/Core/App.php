<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/28
 * Time: 下午2:03
 */


namespace ZPHP\Core;

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
    static public function init($di)
    {
        self::$_di = $di;
        $allList = [];
        foreach (self::$compenontType as $type) {
            self::initConfigList($type, $allList);
            self::initDefaultList($type, $allList);
        }
        try {
            //初始化加载
            foreach ($allList as $key => $value) {
                foreach($value as $k => $v){
                    self::$key($v);
                }
            }
        }catch(\Exception $e){
            echo $e->getMessage()."\n";
        }
    }


    /**
     * 默认的初始化
     * @param $type
     * @throws \Exception
     */
    static public function initDefaultList($type, &$allList){
        $dir = ZPHP::getAppPath().DS.$type;
        if(is_dir($dir)) {
            $classList = Dir::getClass($dir, '/.php$/');
            foreach($classList as $key => $value){
                $value = self::getComponentName($value);
                self::$_di->set($value, $type, $type.'\\'.$value);
                $allList[$type][] = $value;
            }
        }
    }

    /**
     * 配置文件的服务初始化
     * @param $type
     * @throws \Exception
     */
    static public function initConfigList($type, &$allList){
        $modelConfig = Config::get($type);
        if(!empty($modelConfig)) {
            foreach ($modelConfig as $key => $value) {
                $key = self::getComponentName($key);
                self::$_di->set($key, $type, $value);
                $allList[$type][] = $key;
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
    static public function __callStatic($name, $arguments)
    {
        // TODO: Implement __call() method.
        if(empty($arguments)){
            throw new \Exception("组件名不能为空");
        }
        $key = self::getComponentName($arguments[0]);
        $argu = !empty($arguments[1])?$arguments[1]:[];
        return self::get($key, $name, $argu);
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

    /**
     * get相关的依赖class
     * @param $name
     * @param $type
     */
    static public function get($name, $type, $argu=[]){
        $class = self::$_di->get($name, $type, $argu);
        if(empty($class)){
            if(DEBUG){
                throw new \Exception($type.':'.$name.' not found!');
            }
        }
        return $class;
    }


}