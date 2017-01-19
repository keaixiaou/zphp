<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 * 初始化框架相关信息
 */
namespace ZPHP;
use ZPHP\Client\SwoolePid;
use ZPHP\Common\Dir;
use ZPHP\Core\Factory;
use ZPHP\Core\Swoole;
use ZPHP\Monitor\Monitor;
use ZPHP\Platform\Linux;
use ZPHP\Platform\Windows;
use ZPHP\Protocol\Response;
use ZPHP\Template\Template;
use ZPHP\Template\ViewCache;
use ZPHP\View,
    ZPHP\Core\Config,
    ZPHP\Core\Log,
    ZPHP\Common\Debug,
    ZPHP\Common\Formater;

class ZPHP
{
    /**
     * 项目目录
     * @var string
     */
    private static $rootPath;
    private static $tmpPath;
    private static $logPath;
    /**
     * 配置目录
     * @var string
     */
    private static $configPath = 'default';
    private static $appPath ;
    private static $zPath;
    private static $libPath='lib';
    private static $classPath = array();
    private static $os;
    private static $monitorname;
    private static $server_pid;
    private static $server_file;
    private static $appName;

    public static function setOs($os){
        self::$os = $os;
    }

    public static function getOs(){
        return self::$os;
    }

    public static function getRootPath()
    {
        return self::$rootPath;
    }


    public static function getTmpPath(){
        return self::$tmpPath;
    }

    public static function getLogPath(){
        return self::$logPath;
    }

    public static function setRootPath($rootPath)
    {
        self::$rootPath = $rootPath;
        self::$tmpPath = $rootPath.DS.'tmp';
        self::$logPath = self::$tmpPath.DS.'log';
    }

    public static function getConfigPath()
    {
        $dir = self::getRootPath() . DS . 'config' . DS . self::$configPath;
        if (\is_dir($dir)) {
            return $dir;
        }
        return self::getRootPath() . DS . 'config' . DS . 'default';
    }

    public static function setConfigPath($path)
    {
        self::$configPath = $path;
    }

    public static function getAppPath()
    {
        return self::$appPath;
    }

    public static function setAppPath($path)
    {
        self::$appPath = $path;
    }

    public static function getZPath()
    {
        return self::$zPath;
    }

    public static function getLibPath()
    {
        return self::$libPath;
    }

    final public static function autoLoader($class)
    {
        if(isset(self::$classPath[$class])) {
            require self::$classPath[$class];
            return;
        }
        $baseClasspath = \str_replace('\\', DS, $class) . '.php';
        $libs = array(
            self::$appPath,
            self::$zPath
        );
        if(is_array(self::$libPath)) {
            $libs = array_merge($libs, self::$libPath);
        } else {
            $libs[] = self::$libPath;
        }
        foreach ($libs as $lib) {
            $classpath = $lib . DS . $baseClasspath;
            if (\is_file($classpath)) {
                self::$classPath[$class] = $classpath;
                require "{$classpath}";
                return;
            }
        }
    }

    final public static function exceptionHandler(\Exception $exception)
    {
        $trace = $exception->getTrace();
        $info = str_repeat('-', 100) . "\n";
        $info .= "# line:{$exception->getLine()} call:{$exception->getCode()} error message:{$exception->getMessage()}\tfile:{$exception->getFile()}\n";
        foreach ($trace as $k => $t)
        {
            if (empty($t['line']))
            {
                $t['line'] = 0;
            }
            if (empty($t['class']))
            {
                $t['class'] = '';
            }
            if (empty($t['type']))
            {
                $t['type'] = '';
            }
            if (empty($t['file']))
            {
                $t['file'] = 'unknow';
            }
            $info .= "#$k line:{$t['line']} call:{$t['class']}{$t['type']}{$t['function']}\tfile:{$t['file']}\n";
        }
        $info .= str_repeat('-', 100) . "\n";
        echo $info;
        return;
    }

    final public static function fatalHandler()
    {
        $error = \error_get_last();
        if(empty($error)) {
            return;
        }
        if(!in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            return;
        }
        echo Formater::fatal($error);
        return;
//        return Response::display(Formater::fatal($error));
    }

    /**
     * @param $rootPath
     * @param bool $run
     * @param null $configPath
     * @return \ZPHP\Server\IServer
     * @throws \Exception
     */
    public static function run($rootPath, $run=true, $configPath=null)
    {
        global $argv;
        if(empty($argv[1])||!in_array($argv[1],['stop','start','reload','restart','status'])){
            echo "=====================================================\n";
            echo "Usage: php {$argv[0]} start|stop|reload|restart|status\n";
            echo "=====================================================\n";
            exit;
        }else {
            defined('DS') || define('DS', DIRECTORY_SEPARATOR);
            self::$zPath = $rootPath;
            self::setRootPath($rootPath);
            self::setConfigPath('');

            \spl_autoload_register(__CLASS__ . '::autoLoader');
            $config_path = self::getConfigPath();
            Config::load($config_path);
            //设置项目lib目录
            self::$libPath = Config::get('lib_path', self::$zPath . DS . 'lib');
            if ($run && Config::getField('project', 'debug_mode', 0)) {
                Debug::start();
            }
            //设置app目录
            $appPath = Config::get('app_path', self::$appPath);
            self::setAppPath($rootPath.DS.$appPath);
            $eh = Config::getField('project', 'exception_handler', __CLASS__ . '::exceptionHandler');
            \set_exception_handler($eh);
            //致命错误
//            \register_shutdown_function(Config::getField('project', 'fatal_handler', __CLASS__ . '::fatalHandler'));
            if (Config::getField('project', 'error_handler')) {
                \set_error_handler(Config::getField('project', 'error_handler'));
            }

            $timeZone = Config::get('time_zone', 'Asia/Shanghai');
            \date_default_timezone_set($timeZone);

            if(!DEBUG){
                error_reporting(E_ALL^E_NOTICE^E_WARNING);
            }
            self::setOs(new Linux());
            self::$appName = Config::get('project_name');
            if (PHP_OS == 'Linux')
            {
                self::$monitorname = self::$appName;
            }else{
                self::$monitorname = $argv[0];
            }

            self::$server_file = Config::getField('project', 'pid_path').DS.Config::get('project_name').'.pid';
            $pidList = SwoolePid::getPidList(self::$server_file);
            self::$server_pid = !empty($pidList['master'])?$pidList['master']:0;
            self::doCommand($argv[1],$run);
        }

    }


    //执行命令
    protected static function doCommand($argv ,$run){
        if ($argv == 'start') {
            self::start($run);
        }else if ($argv=='stop'){
            self::stop();
            exit( "Service stop success!\n");
        }else if ($argv =='restart'){
            self::stop();
            echo "Service stop success!\nService is starting...\n";
            sleep(2);
            self::start($run);
        }else if ($argv=='reload'){
            self::reload();
        }else if ($argv=='status'){
            self::status();
        }


    }


    protected static function serviceStart(){
        if(!is_file(self::$server_file))file_put_contents(self::$server_file,'');
        Factory::getInstance(\ZPHP\Monitor\Monitor::class, [self::$monitorname, self::$server_file]);
        $vcacheConfig = Config::getField('project', 'view');
        if(!empty($vcacheConfig['tag'])) {
            ViewCache::init();
            ViewCache::cacheDir(self::getAppPath() . DS . 'view');
        }
    }

    protected static function start($run){
        if(empty(self::$server_pid)){
            self::serviceStart();
            $serverMode = Config::get('server_mode', 'Http');
            //寻找server的socket适配器
            $service = Server\Factory::getInstance($serverMode);
            if ($run) {
                echo ( "Service startting success!\n");
                $service->run();
            } else {
                return $service;
            }
        }else{
            echo ( "Service already started!\n");
        }

//        if ($run && Config::getField('project', 'debug_mode', 0)) {
//            Debug::end();
//        }
    }

    protected static function stop(){
        if(empty(self::$server_pid)){
            echo ("Service has shut down!\n");
        }else{
            $res = self::getOs()->kill(self::$server_pid, SIGTERM);
            self::$server_pid = 0;
        }
        if(is_file(self::$server_file)){
            unlink(self::$server_file);
        }

    }

    protected static function reload(){
        if (empty(self::$server_pid))
        {
            exit("Server is not running");
        }
        self::$os->kill(self::$server_pid, SIGUSR1);
        exit;
    }

    protected static function status(){
        if(empty(self::$server_pid)){
            exit(self::$appName." Has been Shut Down!\n");
        }
        $monitor = Factory::getInstance(\ZPHP\Monitor\Monitor::class, [self::$monitorname, self::$server_file]);
        $monitor->outPutNowStatus();
    }





}
