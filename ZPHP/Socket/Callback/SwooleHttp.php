<?php


namespace ZPHP\Socket\Callback;

use ZPHP\Core\Db,
    ZPHP\Core\Config,
    ZPHP\Core\Swoole,
    ZPHP\Core\Log;
use ZPHP\Protocol;
use ZPHP\Socket\Callback\Swoole as CSwoole;


abstract class SwooleHttp extends CSwoole
{

    public function onReceive()
    {
        throw new \Exception('http server must use onRequest');
    }

    public function onWorkerStart($server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);
        set_error_handler(array($this, 'onErrorHandle'), E_USER_ERROR);
        register_shutdown_function(array($this, 'onErrorShutDown'));
    }


    /**
     * http 请求回调函数
     * @param $request
     * @param $response
     */
    public function doRequest($request, $response)
    {
        $this->onRequest($request, $response);
        $this->afterResponese();
    }


    function onErrorShutDown()
    {
        $error = error_get_last();
        if (!isset($error['type'])) return;
        switch ($error['type'])
        {
            case E_ERROR :
            case E_PARSE :
            case E_USER_ERROR:
            case E_CORE_ERROR :
            case E_COMPILE_ERROR :
                break;
            default:
                return;
        }

        $this->errorResponse($error);
    }


    /**
     * 捕获set_error_handle错误
     */
    public function onErrorHandle($errno, $errstr, $errfile, $errline){
        $error = array(
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
        );
        $this->errorResponse($error);
    }



    public function errorResponse($error){
        $errorMsg = DEBUG===true?"{$error['message']} ({$error['file']}:{$error['line']})":'application internal error!';
        Log::write("errorResponse:".$errorMsg,Log::ERROR, true);
        $this->afterResponese();
    }

    protected function afterResponese(){
        if (ob_get_contents()) ob_end_clean();
    }

    abstract public function onRequest($request, $response);

    public function onTask($server, $taskId, $fromId, $data)
    {
    }
}
