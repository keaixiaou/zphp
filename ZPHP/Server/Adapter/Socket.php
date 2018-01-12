<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Server\Adapter;
use ZPHP\Core\Config;
use ZPHP\Core\Di;
use ZPHP\Server\IServer;
use ZPHP\Socket\Factory as SFactory;

class Socket implements IServer
{
    public function run()
    {
        //
        $config = Config::get('server');
        if (empty($config)) {
            throw new \Exception("socket config empty");
        }

        //寻找swoole_socket
        //构造,并且生成该类的client-swooleHttp
        $socket = SFactory::getInstance($config['adapter'], $config);
        if(method_exists($socket, 'setClient')) {
            $client = Di::make("\\ZPHP\\Client\\Swoole".ucfirst($config['server_type']));
            $socket->setClient($client);
        }
        $socket->run();
    }
}