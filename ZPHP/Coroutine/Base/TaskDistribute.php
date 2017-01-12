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
    static protected $taskList;
    static protected $allTaskNum=0;

    /**
     * task进程管理的初始化
     * @param array $taskTypeArray
     * @throws \Exception
     */
    static public function init(){
        $taskCoroutineConfig = Config::get('task_coroutine');
        $taskTypeArray = isset($taskCoroutineConfig)?$taskCoroutineConfig:['mongo','memcached'];
        self::$allTaskNum = 0;
        self::$taskList = [];
        $socketConfig = Config::get('socket');
        $workNum = intval($socketConfig['worker_num']);
        $taskId = 0;
        foreach($taskTypeArray as $task){
            $asynCount = intval(Config::getField($task, 'asyn_max_count'));
            self::$allTaskNum += $workNum* $asynCount;
            $i = 0;
            while($i<$workNum){
                $j = 0;
                while($j< $asynCount){
                    self::$taskList[$task][$i][] = $taskId;
                    $taskId ++;
                    $j++;
                }
                $i ++;
            }
        }
        self::$allTaskNum += intval($socketConfig['task_worker_num']);
    }

    /**
     * 返回总的task进程数量
     * @return int
     */
    static public function getAllTaskNum(){
        return self::$allTaskNum;
    }

    /**
     * 返回某一个task的进程编号
     * @param $name
     * @return mixed
     */
    static public function getSingleTaskNum($name){
        return !empty(self::$taskList[$name])?self::$taskList[$name]:[];
    }
}