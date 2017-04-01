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
    static protected $pidFileName;

    /**
     * 初始化
     * @param $path
     * @param $name
     * @param $setting
     */
    public static function init($path, $name, $setting){
        self::$pidth = $path;
        self::$pidFileName = self::$pidth.$name;
    }


    static public function getFileName(){
        return self::$pidFileName;
    }
    /**
     * 获取pidList
     * @param $file
     * @return array|mixed
     */
    public static function getPidList($file){
        if(is_file($file)){
            $fileData = file_get_contents($file);
            $pidList = json_decode($fileData, true);
        }
        return !empty($pidList)?$pidList:[];
    }


    /**
     * 输入pidlist
     * @param $pidList
     * @param $file
     */
    public static function putPidList( $pidList){

        if(!file_exists(self::$pidFileName))return;
        $fp = fopen(self::$pidFileName,"r+");
        while($fp){
            if(flock($fp, LOCK_EX)){
                $myPidList = self::getPidList(self::$pidFileName);
                $myPidList = self::mergeList($myPidList, $pidList);
                self::writePidFile($myPidList);
                flock($fp, LOCK_UN);
                break;
            }else{
                usleep(1000);
            }
        }
        if($fp)fclose($fp);

    }

    /**
     * @param $type
     * @param $pid
     * @param int $status
     * @param string $taskType
     * @return array  = ['work'=>[['pid'=>1,'status'=>0]]];
     */
    public static function makePidList($type, $pid, $status=1, $taskType=''){
        return [$type =>
            [['pid' => $pid, 'status' => $status, 'type'=>$taskType]]
        ];
    }


    public static function getMasterPid($file){
        $pidList = self::getPidList($file);
        $master = !empty($pidList)?key($pidList['master']):0;
        return !empty($master)?$master:0;
    }

    /**
     * @param $allPidList
     * @param $pidlist = ['work'=>[['pid'=>1,'status'=>0]]];
     * @return mixed
     */
    static protected function mergeList($allPidList, $pidlist){
        foreach($pidlist as $type => $pidList){
            if(is_array($pidList)){
                foreach($pidList as $key => $pidInfo){
                    $allPidList[$type][$pidInfo['pid']] = $pidInfo;
                }
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