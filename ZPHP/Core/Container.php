<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 */
namespace ZPHP\Core;

class Container
{
    private static $instances = [];
    private static $allObject = [];
    private function __construct()
    {
    }

    public static function getInstance()
    {
        if(empty(self::$instances)){
            self::$instances = new self();
        }
        return self::$instances;
    }

    public function make($objectName, $params = null){
        $keyName = $objectName;
        if (is_array($params)&&!empty($params['_prefix'])) {
            $keyName .= $params['_prefix'];
        }
        if (isset(self::$allObject[$keyName])) {
            return self::$allObject[$keyName];
        }

        if (!\class_exists($objectName)) {
            throw new \Exception($objectName.' not exsist!');
//            return null;
        }
        if (empty($params)) {
            self::$allObject[$keyName] = new $objectName();
        } else {
            self::$allObject[$keyName] = new $objectName($params);
        }
        return self::$allObject[$keyName];
    }


    //用来重载controller文件
    public function reload($className, $params=null){
        $keyName = $className;
        if (!empty($params['_prefix'])) {
            $keyName .= $params['_prefix'];
        }

        $controller_file = ROOTPATH.'/apps/'.str_replace('\\','/',$className).'.php';
        if(!is_file($controller_file)){
            throw new \Exception("no file {$controller_file}");
        }
//        runkit_import($controller_file, RUNKIT_IMPORT_CLASS_METHODS|RUNKIT_IMPORT_OVERRIDE);

        if (!\class_exists($className)) {
            throw new \Exception("no class {$className}");
        }
        $class = $params? new $className($params): new $className();
        self::$allObject[$keyName] = $class;
        return self::$allObject[$keyName];
    }
}
