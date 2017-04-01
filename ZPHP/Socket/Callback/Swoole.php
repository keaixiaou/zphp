<?php


namespace ZPHP\Socket\Callback;

use ZPHP\Client\SwoolePid;
use ZPHP\Coroutine\Base\TaskDistribute;
use ZPHP\Socket\ICallback;
use ZPHP\Core\Config as ZConfig;
use ZPHP\Core\Log;
use ZPHP\Protocol;
use ZPHP\ZPHP;


abstract class Swoole implements ICallback
{

    protected $protocol;
    protected $pidFile;
    protected $serv;


    public function init($server){
        $pidPath = ZConfig::getField('project', 'pid_path').DS;
        $pidname = ZConfig::get('project_name') . '.pid';
        SwoolePid::init($pidPath, $pidname, $server->setting);
        if(!empty($pidPath)){
            $this->pidFile = $pidPath . DS . $pidname;
        }
        if(!is_dir(ZPHP::getLogPath())){
            @mkdir(ZPHP::getLogPath(), 0777, true);
        }
        $this->serv = $server;
    }
    /**
     * @throws \Exception
     * @desc 服务启动，设置进程名及写主进程id
     */
    public function onStart()
    {
        $server = func_get_args()[0];
        swoole_set_process_name(ZConfig::get('project_name') . ' running ' .
            ZConfig::getField('socket', 'server_type', 'tcp') .
            '://' . ZConfig::getField('socket', 'host') .
            ':' . ZConfig::getField('socket', 'port')
            . " time:".date('Y-m-d H:i:s'));
        $pidList = SwoolePid::makePidList('master', $server->master_pid);
        $this->putPidList($pidList);

    }

    /**
     * @throws \Exception
     */
    public function onShutDown()
    {
        if (!empty($this->pidFile) && is_file($this->pidFile)) {
            unlink($this->pidFile);
        }
    }

    /**
     * @param $server
     * @throws \Exception
     * @desc 服务启动，设置进程名
     */
    public function onManagerStart($server)
    {
        swoole_set_process_name(ZConfig::get('project_name') .
            ' manager:' . $server->manager_pid);
        $pidList = SwoolePid::makePidList('manager', $server->manager_pid);
        $this->putPidList($pidList);
    }


    /**
     * @param $server
     * @throws \Exception
     * @desc 服务关闭，删除进程id文件
     */
    public function onManagerStop($server)
    {
    }

    public function onWorkerStart($server, $workerId)
    {
        $workNum = ZConfig::getField('socket', 'worker_num');
        if($server->taskworker){
            $taskId = $server->worker_id - $workNum;
            $taskAsyName = TaskDistribute::getAsyNameFromTaskId($taskId);
            swoole_set_process_name(ZConfig::get('project_name')
                . " task num: {$taskId}"." {$taskAsyName}");
            $pidList = SwoolePid::makePidList('task', $server->worker_pid, 1, $taskAsyName);
        }else{
            swoole_set_process_name(ZConfig::get('project_name')
                . " work num: {$server->worker_id}");
            $pidList = SwoolePid::makePidList('work', $server->worker_pid);
        }

        $this->putPidList($pidList);
        if(function_exists('opcache_reset')) {
            opcache_reset();
        }


    }

    public function onWorkerStop($server, $workerId)
    {
        Log::clear();
        $type = !empty($server->taskworker)?'task':'work';
        $pidList = SwoolePid::makePidList($type, $server->worker_pid, 0);
        $this->putPidList($pidList);
    }

    public function onWorkerError($server, $workerId, $workerPid, $errorCode)
    {
        $workNum = ZConfig::getField('socket', 'worker_num');
        $type = $workerId>=$workNum?'task':'work';
        $pidList = SwoolePid::makePidList($type, $workerPid, 0);
        $this->putPidList($pidList);
    }


    public function onConnect()
    {

    }

    public function doReceive($server, $fd, $from_id, $data)
    {
        Protocol\Request::setFd($fd);
        $this->onReceive($server, $fd, $from_id, $data);
    }

    abstract public function onReceive();

    public function onPacket($server, $data, $clientInfo)
    {

    }

    public function onClose()
    {

    }


    public function onTask($server, $taskId, $fromId, $data)
    {
    }

    public function onFinish($server, $taskId, $data)
    {

    }

    public function onPipeMessage($server, $fromWorerId, $data)
    {

    }



    protected function putPidList($pidList){
        $pidList = empty($pidList)?[]:$pidList;
        SwoolePid::putPidList( $pidList);
    }

}
