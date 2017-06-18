<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 * config配置处理
 */

namespace ZPHP\Core;

use ZPHP\Common\Dir;
use ZPHP\Common\Log;
use ZPHP\ZPHP;

class Config
{

    private static $config=[];

    public static function load($configPath)
    {
        $configPath = rtrim($configPath, DS).DS;
        $pathType = [ZPHP::getMode(), 'common'];
        $files = [];
        foreach ($pathType as $type){
            $tmpPath = $configPath.$type;
            if(is_dir($tmpPath)) {
                $tmpFiles = Dir::tree($tmpPath, "/.php$/");
                $files = array_merge($files, $tmpFiles);
            }
        }
        $config = array();
        if (!empty($files)) {
            foreach ($files as $file) {
                $config += include "{$file}";
            }
        }
        self::$config = $config;
        return self::$config;
    }

    public static function loadFile($file, $cutLen)
    {
        $config = [];
        $config += include "{$file}";
        $configNameArray = explode(DS, substr(trim(substr($file, $cutLen), DS), 0, -4));
        $top = array_shift($configNameArray);
        $fileConfig = [$top => self::getNextConfig($configNameArray, $config)];

        self::$config = self::configMerge(self::$config, $fileConfig);
        return $config;
    }

    private static function getNextConfig($nextArray, $config){
        if(empty($nextArray))
            return $config;
        $next = array_shift($nextArray);
        return [$next => self::getNextConfig($nextArray, $config)];
    }


    private static function configMerge($config, $addConfig){
        if(empty($config) || empty($addConfig)){
            return empty($config)?$addConfig:$config;
        }

        foreach($addConfig as $key => $value){
            if(!isset($config[$key])){
                return array_merge($config, $addConfig);
            }else{
                $tmp = $config[$key];
                unset($config[$key]);
                return array_merge($config, [$key => self::configMerge($tmp, $value)]);
            }
        }
    }


    /**
     * @param $key
     * @param null $default
     * @param bool $throw
     * @return mixed|null
     * @throws \Exception
     */
    public static function get($key, $default = null, $throw = false)
    {
        $result = isset(self::$config[$key]) ? self::$config[$key] : $default;
        if ($throw && is_null($result)) {
            throw new \Exception("{key} config empty");
        }
        return $result;
    }

    /**
     * 分割keys
     * @param $keys
     * @param null $default
     * @param bool $throw
     * @return null
     * @throws \Exception
     */
    public static function getByStr($keys, $default = null, $throw = false)
    {
        $exist = true;
        $configValue = $default;
        $keyArray = explode('.', $keys);
        if(!empty($keyArray)){
            $configValue = self::$config;
            foreach ($keyArray as $key){
                if(!isset($configValue[$key])){
                    $exist = false;
                    $configValue = $default;
                    break;
                }else{
                    $configValue = $configValue[$key];
                }
            }
        }else{
            $exist = false;
        }

        if (!$exist && $throw) {
            throw new \Exception("{key} config empty");
        }
        return $configValue;
    }

    public static function set($key, $value, $set = true)
    {
        if ($set) {
            self::$config[$key] = $value;
        } else {
            if (empty(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }

        return true;
    }

    public static function getField($key, $field, $default = null, $throw = false)
    {
        $result = isset(self::$config[$key][$field]) ? self::$config[$key][$field] : $default;
        if ($throw && is_null($result)) {
            throw new \Exception("{key} config empty");
        }
        return $result;
    }

    public static function setField($key, $field, $value, $set = true)
    {
        if ($set) {
            self::$config[$key][$field] = $value;
        } else {
            if (empty(self::$config[$key][$field])) {
                self::$config[$key][$field] = $value;
            }
        }

        return true;
    }

    public static function all()
    {
        return self::$config;
    }

}
