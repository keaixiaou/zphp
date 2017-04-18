<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/3
 * Time: 下午1:57
 */

namespace ZPHP\Template;

use ZPHP\Common\Dir;
use ZPHP\Core\Container;
use ZPHP\Core\Factory;
use ZPHP\ZPHP;

class ViewCache{
    static protected $originPath;
    static protected $cachePath;
    static protected $fileCacheTime=[];
    static protected $template;


    /**
     * init 初始化
     */
    static public function init(){
        self::$template = Container::Template('Template');
        self::$originPath = ZPHP::getAppPath().DS.'view'.DS;
        self::$cachePath = ZPHP::getTmpPath().DS.'view'.DS;
    }

    /**
     * 检查文件缓存并且缓存
     * @param $fileArray
     */
    static public function checkCache($fileArray)
    {
        foreach ($fileArray as $file) {
            $cacheFile = self::$cachePath.$file;
            if (empty(self::$fileCacheTime[$file]) || self::$fileCacheTime[$file] < filemtime(self::$originPath.$file)
            || !is_file($cacheFile)){
                self::cacheFile($file);
            }
        }
    }


    /**
     * 缓存单一文件
     * @param $file
     */
    static protected function cacheFile($file){
        $orginFile = self::$originPath.$file;
        $orginContent = file_get_contents($orginFile);
        $cacheContent = self::$template->parse($orginContent);
        $cacheFile = self::$cachePath.$file;
        $fileDir = substr($cacheFile, 0 , strripos($cacheFile, DS));
        if(!is_dir($fileDir)){
            @mkdir($fileDir, 0777 ,true);
        }
        file_put_contents($cacheFile, $cacheContent);
        self::$fileCacheTime[$file] = filemtime(self::$cachePath.$file);
    }

    /**
     * 缓存目录下的所有html文件
     * @param $dir
     * @throws \Exception
     */
    static public function cacheDir($dir){
        $fileArray = Dir::getFileName($dir, '/.html$/');
        foreach($fileArray as $file){
            self::cacheFile($file);
        }
    }


}