<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 * 初始化框架相关信息
 */
namespace ZPHP;
use ZPHP\Client\SwoolePid;
use ZPHP\Common\Dir;
use ZPHP\Common\DocParser;
use ZPHP\Core\Container;
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
    ZPHP\Core\DI,
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
    private static $syslogPath;
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


    public static function setSystemLog($logpath){
        self::$syslogPath = $logpath;
    }

    public static function getSystemLog(){
        return self::$syslogPath;
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

    final public static function exceptionHandler()
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
        $errorMsg = "{$error['message']} ({$error['file']}:{$error['line']})";
        exit($errorMsg);
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
        define("ZPHP_VERSION", 2.1);
        global $argv;
        if(empty($argv[1])||!in_array($argv[1],
                ['stop','start','reload','restart','status','doc'])){
            echo "=====================================================\n";
            echo "Usage: php {$argv[0]} start|stop|reload|restart|status\n";
            echo "=====================================================\n";
            exit;
        }else {
            defined('DS') || define('DS', DIRECTORY_SEPARATOR);
            self::$zPath = $rootPath;
            self::setRootPath($rootPath);
            self::setConfigPath('');

            \spl_autoload_register(__CLASS__.'::autoLoader');
            $config_path = self::getConfigPath();
            Config::load($config_path);
            //设置app目录
            $appPath = Config::get('app_path', self::$appPath);
            self::setAppPath($rootPath.DS.$appPath);

            //致命错误
            register_shutdown_function(__CLASS__ . '::exceptionHandler');

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
            self::$server_pid = SwoolePid::getMasterPid(self::$server_file);
            Container::init(DI::getInstance());
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
            self::start($run);
        }else if ($argv=='reload'){
            self::reload();
        }else if ($argv=='status'){
            self::status();
        }else if ($argv=="doc"){
            self::doc();
        }


    }


    protected static function serviceStart(){
        if(!file_exists(self::$server_file))file_put_contents(self::$server_file,'');
        Container::Monitor('Monitor', [self::$monitorname, self::$server_file]);
//        Factory::getInstance(\ZPHP\Monitor\Monitor::class, );
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
            $service = Container::Server('Adapter/'.$serverMode);
            if ($run) {
                echo ( "Service startting success!\n");
                $service->run();
            } else {
                return $service;
            }
        }else{
            echo ( "Service already started!\n");
        }
    }

    protected static function stop(){
        if(empty(self::$server_pid)){
            echo ("Service has shut down!\n");
        }else{
            $res = self::getOs()->kill(self::$server_pid, SIGTERM);
            if(PHP_OS=='Linux'){
                $pidFile = '/proc/'.self::$server_pid.'/status';
                while(true) {
                    $res = file_exists($pidFile);
                    if (!$res) {
                        break;
                    }
                    usleep(100000);
                }
            }else{
                usleep(200000);
                usleep(200000);
            }
            self::$server_pid = 0;
        }
        if(file_exists(self::$server_file)){
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
        $monitor = Container::Monitor('Monitor', [self::$monitorname, self::$server_file]);
//        $monitor = Factory::getInstance(\ZPHP\Monitor\Monitor::class, );
        $monitor->outPutNowStatus();
    }


    protected static function doc(){
        $filePath = self::getAppPath().DS ."controller";
        $filter = '\\controller';
        $docPath = self::getRootPath().DS.Config::get('doc_path','doc');
        $docParse = new DocParser();
        $docParse->makeDocHtml($filePath, $filter, $docPath);
        echo  "文档生成成功!\n";
        echo "Html文档在".$docPath.DS."html,配置nginx 目录即可访问\n";
        echo "MarkDown文档在".$docPath.DS."markdown\n";

    }



}
