<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/26
 * Time: 下午3:23
 */


namespace ZPHP\Client;

use ZPHP\Core\Config;
use ZPHP\Core\Log;

abstract class SwoolePid{

    static protected $pidth;
    static protected $pidList;
    static protected $pidFileName;
    static protected $lockfile;

    /**
     * 初始化
     * @param $path
     * @param $name
     * @param $setting
     */
    static public function init($path, $name, $setting){
        self::$pidth = $path;
        self::$pidFileName = self::$pidth.$name;
        self::$lockfile = self::$pidth.'tmp.lock';
        self::makeTmpList($setting);
    }

    /**
     * 获取pidList
     * @param $file
     * @return array|mixed
     */
    static public function getPidList($file){
        if(is_file($file)){
            $fileData = file_get_contents($file);
            $pidList = json_decode($fileData, true);
        }
        return !empty($pidList)?$pidList:[];
    }


    /**
     * 生成临时文件列表
     * @param $setting
     */
    static public function makeTmpList($setting){
        self::$pidList[] = self::$pidth.'master';
        self::$pidList[] = self::$pidth.'manager';
        $i = 0;
        //        'task_worker_num' => 1,
        while($i<$setting['worker_num']){
            self::$pidList[] = self::$pidth.'work'.$i;
            $i++;
        }
        $i = 0;
        while($i<$setting['task_worker_num']){
            self::$pidList[] = self::$pidth.'task'.$i;
            $i++;
        }

    }

    /**
     * 输入pidlist
     * @param $pidList
     * @param $file
     */
    static public function putPidList( $pidList){

        if(!file_exists(self::$pidFileName))return;
        $fp = fopen(self::$pidFileName,"r+");
        while($fp){
            if(flock($fp, LOCK_EX)){
                $myPidList = self::getPidList(self::$pidFileName);
                foreach($pidList as $key => $value){
                    $myPidList = self::mergeList($myPidList, $value);
                }
                self::writePidFile($myPidList);
                flock($fp, LOCK_UN);
                break;
            }else{
                usleep(1000);
            }
        }
        if($fp)fclose($fp);

    }


    static protected function mergeList($allPidList, $pidlist){
        foreach($pidlist as $k => $pidstatus){
            if(is_array($pidstatus) && !empty($allPidList[$k])){
                foreach($pidstatus as $p => $s){
                    $allPidList[$k][$p] = $s;
                }
//                Log::write('tmpList:'.print_r($allPidList, true));
            }else{
                $allPidList[$k] = $pidstatus;
            }
        }
        return $allPidList;
    }


    /**
     * 写入pid文件
     * @param $pidList
     */
    static protected function writePidFile($pidList){
        file_put_contents(self::$pidFileName, json_encode($pidList));
    }

}