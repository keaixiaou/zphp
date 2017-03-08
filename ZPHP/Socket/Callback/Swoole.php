<?php


namespace ZPHP\Socket\Callback;

use ZPHP\Client\SwoolePid;
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
        swoole_set_process_name(ZConfig::get('project_name') . ' server running ' .
            ZConfig::getField('socket', 'server_type', 'tcp') .
            '://' . ZConfig::getField('socket', 'host') .
            ':' . ZConfig::getField('socket', 'port')
            . " time:".date('Y-m-d H:i:s')."  master:" . $server->master_pid);

        $this->putPidList(['master'=>['master' => $server->master_pid]]);

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
            ' server manager:' . $server->manager_pid);
        $this->putPidList(['manager'=>['manager' => $server->manager_pid]]);
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
        $pidList = [];
        if($server->taskworker){
            swoole_set_process_name(ZConfig::get('project_name') . " server tasker  num: ".($server->worker_id - $workNum)." pid " . $server->worker_pid);
            $pidList['task'.($server->worker_id - $workNum)] = ['task'=>[$server->worker_pid=>1]];
        }else{
            swoole_set_process_name(ZConfig::get('project_name') . " server worker  num: {$server->worker_id} pid " . $server->worker_pid);
            $pidList['work'.$server->worker_id] = ['work'=>[$server->worker_pid=>1]];
        }

        $this->putPidList($pidList);
        if(function_exists('opcache_reset')) {
            opcache_reset();
        }


    }

    public function onWorkerStop($server, $workerId)
    {
        Log::clear();
        $pidList = [];
        $workNum = ZConfig::getField('socket', 'worker_num');
        if(!empty($server->taskworker)){
            $pidList['task'.($workerId-$workNum)] = ['task'=>[$server->worker_pid => 0]];
        }else{
            $pidList['work'.$workerId] = ['work'=>[$server->worker_pid => 0]];
        }
        $this->putPidList($pidList);
    }

    public function onWorkerError($server, $workerId, $workerPid, $errorCode)
    {
        $pidList = [];
        $workNum = ZConfig::getField('socket', 'worker_num');
        if($workerId>=$workNum){
            $pidList['task'.($workerId - $workNum)] = ['task'=>[$workerPid=>0]];
        }else{
            $pidList['work'.$workerId] = ['work'=>[$workerPid=>0]];
        }
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
