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
    ZPHP\Core\DI;

class ZPHP
{
    private static $cmdArray = ['stop','start','reload','restart','status','doc'];
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
    private static $mode = 'local';
    private static $configPath = 'config';
    private static $appPath;
    private static $zPath;
    private static $libPath='lib';
    private static $classPath = array();
    private static $os;
    private static $monitorname;
    private static $server_pid;
    private static $server_file;
    private static $appName;


    public static function init(){
        define("ZPHP_VERSION", 2.2);
        defined('DS') || define('DS', DIRECTORY_SEPARATOR);
        $mode = get_cfg_var('zhttp.RUN_MODE');
        $mode = in_array($mode, ['qatest', 'online', 'local','pre'])?$mode:'qatest';
        self::setMode($mode);
        $debug = empty(get_cfg_var('zhttp.DEBUG'))?false:true;
        define('DEBUG', $debug);
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
        if(empty($argv[1])||!in_array($argv[1], self::$cmdArray)){
            self::getHelp($argv[1]);
        }else {
            self::init();
            self::$zPath = $rootPath;
            self::setRootPath($rootPath);

            \spl_autoload_register(__CLASS__.'::autoLoader');
            $config_path = self::getConfigPath();
            $allConfig = Config::load($config_path);

            //设置app目录
            $projectConfig = Config::get('project', null, true);
            $appPath = $projectConfig['app_path'];
            self::setAppPath($rootPath.DS.$appPath);

            //致命错误

            $timeZone = empty($projectConfig['time_zone'])?
                'Asia/Shanghai':$projectConfig['time_zone'];
            \date_default_timezone_set($timeZone);

            if(!DEBUG){
                error_reporting(E_ALL^E_NOTICE^E_WARNING);
            }
            self::setOs(new Linux());
            self::$appName = $projectConfig['project_name'];
            if (PHP_OS == 'Linux')
            {
                self::$monitorname = self::$appName;
            }else{
                self::$monitorname = $argv[0];
            }

            self::$server_file = $projectConfig['pid_path']
                .DS.$projectConfig['project_name'].'.pid';
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
            $service = Container::Server('Adapter/Socket');
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
        $docPath = self::getRootPath().DS.Config::getField('project', 'doc_path','doc');
        $docParse = new DocParser();
        $docParse->makeDocHtml($filePath, $filter, $docPath);
        echo  "文档生成成功!\n";
        echo "Html文档在".$docPath.DS."html,配置nginx 目录即可访问\n";
        echo "MarkDown文档在".$docPath.DS."markdown\n";

    }

    protected static function getHelp($param){
        echo "=====================================================\n";
        echo "Usage: php {$param} start|stop|reload|restart|status\n";
        echo "=====================================================\n";
        exit;
    }

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
        return self::getRootPath() . DS .self::$configPath;
    }

    public static function setConfigPath($path)
    {
        self::$configPath = $path;
    }

    public static function setMode($mode){
        self::$mode = $mode;
    }
    public static function getMode(){
        return self::$mode;
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

}
