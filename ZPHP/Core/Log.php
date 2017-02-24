<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/7/14
 * Time: 下午2:14
 */


namespace ZPHP\Core;

use ZPHP\ZPHP;

abstract class Log {
    const TRACE   = 0;
    const INFO    = 1;
    const NOTICE  = 2;
    const WARN    = 3;
    const ERROR   = 4;
    private static $log=[];
    private static $syslog=[];

    protected static $level_str = array(
        'TRACE',
        'INFO',
        'NOTICE',
        'WARN',
        'ERROR',
    );


    //写日志
    static public function write($msg, $level=self::ERROR, $system=false){
        $level_str = self::$level_str[$level];
        $timeArray = explode(' ', microtime());
        $message = date('Y-m-d H:i:s').substr($timeArray[0],1)." {$level_str}-".$msg."\n";
        if($system){
            self::$syslog[] = $message;
            if(DEBUG!==true && count(self::$syslog)<100)return;
        }else {
            self::$log[] = $message;
            if(DEBUG!==true && count(self::$log)<100)return;
        }
        self::reallyWrite($system);
    }

    /**
     * 实际写日志
     */
    static protected function reallyWrite($type=false){
        if($type===false){
            $str = implode("", self::$log);
            $filePath = ZPHP::getLogPath().DS.'app';
            if(!is_dir($filePath)){
                mkdir($filePath, 0755, true);
            }
            $fileName = $filePath.'/'.date('Y-m-d').'.log';
            error_log($str, 3, $fileName);
            self::$log = [];
        }else{
            $str = implode("", self::$syslog);
            $fileName = ZPHP::getSystemLog();
            error_log($str, 3, $fileName);
            self::$syslog = [];
        }
    }

    /**
     * 写完日志(一般用在workstop)
     */
    static public function clear(){
        if(!empty(self::$log)){
            self::reallyWrite();
        }
        if(!empty(self::$syslog)){
            self::reallyWrite(true);
        }
    }
}


