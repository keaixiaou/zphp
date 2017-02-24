<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/2/23
 * Time: 下午1:54
 */
namespace ZPHP\Coroutine\Task;

use ZPHP\Core\Log;
use ZPHP\Coroutine\Base\CoroutineBase;

class TaskCoroutine extends CoroutineBase{
    public function __construct(TaskAsynPool $taskAsynPool)
    {
        $this->ioVector = $taskAsynPool;
    }


    public function nocallback($data){
        $this->ioVector->command(null, $data);
    }

}