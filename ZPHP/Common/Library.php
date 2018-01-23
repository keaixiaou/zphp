<?php
/**
 * Created by PhpStorm.
 * author: zhaoye(zhaoye@youzan.com)
 * Date: 2018/1/22
 * Time: 上午10:56
 */

function sysEcho($info){
    \ZPHP\Core\Log::write($info."\n", \ZPHP\Core\Log::ERROR, true);
}

function getContext($key, $default = null){
    return new \ZPHP\Coroutine\Base\CoroutineGlobal(function (\ZPHP\Coroutine\Base\CoroutineTask $task) use ($key, $default) {
        $res = $task->getContext()->getAll($key);
        $task->send($res);

        return \ZPHP\Coroutine\Base\Signa::SCONTINUE;
    });
}

function setContext($key, $value){
    return new \ZPHP\Coroutine\Base\CoroutineGlobal(function (\ZPHP\Coroutine\Base\CoroutineTask $task) use ($key, $value) {
        $task->send($task->getContext()->set($key, $value));
        return \ZPHP\Coroutine\Base\Signa::SCONTINUE;
    });
}

function taskSleep($ms){
    return new \ZPHP\Coroutine\Base\CoroutineGlobal(function (\ZPHP\Coroutine\Base\CoroutineTask $task) use ($ms) {
        $timeTickId = \swoole_timer_after($ms, function () use($task){
            $task->work();
        });
        $task->send($timeTickId);
        return \ZPHP\Coroutine\Base\Signa::SBREAK;
    });
}