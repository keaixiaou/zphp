<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/7
 * Time: 下午3:33
 */

namespace ZPHP\Coroutine\Base;

use ZPHP\Core\Config;
use ZPHP\Core\Log;

class TaskDistribute {
    protected static $taskList;
    protected static $allTaskNum=0;
    private static $_taskName = 'task';
    /**
     * task进程管理的初始化
     * @param array $taskTypeArray
     * @throws \Exception
     */
    public static function init(){
        $taskTypeArray = Config::getField('project', 'task_coroutine',['mongo','memcached']);
        self::$allTaskNum = 0;
        self::$taskList = [];
        $socketConfig = Config::get('server');
        $workNum = intval($socketConfig['worker_num']);
        $singleTaskWorkerNum = !empty($socketConfig['single_task_worker_num'])?
            intval($socketConfig['single_task_worker_num']):0;
        $taskId = 0;
        $i = 0;
        while($i<$workNum){
            //用于mongo和memcached的task
            foreach($taskTypeArray as $task){
                $asynCount = intval(Config::getField($task, 'asyn_max_count'));
                self::$allTaskNum += $asynCount;
                $j = 0;
                while($j< $asynCount){
                    self::$taskList[$task][$i][] = $taskId;
                    $taskId ++;
                    $j++;
                }
            }
            //普通task
            $single = 0;
            self::$allTaskNum += $singleTaskWorkerNum;
            while($single<$singleTaskWorkerNum){
                self::$taskList[self::$_taskName][$i][] = $taskId;
                $taskId ++;
                $single++;
            }
            $i ++;
        }
    }

    /**
     * 返回总的task进程数量
     * @return int
     */
    public static function getAllTaskNum(){
        return self::$allTaskNum;
    }

    /**
     * 返回某一个task的进程编号
     * @param $name
     * @return mixed
     */
    public static function getSingleTaskNum($name){
        return !empty(self::$taskList[$name])?self::$taskList[$name]:[];
    }

    public static function getTaskList(){
        return self::$taskList;
    }

    public static function getAsyNameFromTaskId($taskId){
        $taskAsyName = '';
        foreach(self::$taskList as $asyName => $asypList){
            foreach($asypList as $workid => $tpidList){
                foreach($tpidList as $k => $v){
                    if($v==$taskId){
                        return $asyName;
                    }
                }
            }
        }
        return $taskAsyName;
    }
}