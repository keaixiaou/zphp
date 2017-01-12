<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/1/6
 * Time: 下午4:17
 */

namespace ZPHP\Coroutine\Base;

class TaskParam{
    public $taskId;
    public $config;

    public function __construct($taskId, $config)
    {
        $this->taskId = $taskId;
        $this->config = $config;
    }

}