<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 * 初始化框架相关信息
 */
namespace ZPHP;
use ZPHP\Client\SwoolePid;
use ZPHP\Common\Dir;
use ZPHP\Core\Swoole;
use ZPHP\Platform\Linux;
use ZPHP\Platform\Windows;
use ZPHP\Protocol\Response;
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
    /**
     * 配置目录
     * @var string
     */
    private static $configPath = 'default';
    private static $appPath = 'apps';
    private static $zPath;
    private static $libPath='lib';
    private static $classPath = array();
    private static $os;
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


    public static function setRootPath($rootPath)
    {
        self::$rootPath = $rootPath;
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
            self::$rootPath . DS . self::$appPath,
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
            self::setAppPath($appPath);
            define("APPPATH", ROOTPATH.DS.self::$appPath);
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

            if (PHP_OS == 'WINNT')
            {
                self::setOs(new Windows());
            }else{
                self::setOs(new Linux());
            }
            self::$appName = Config::get('project_name');
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


    protected static function start($run){
        if(empty(self::$server_pid)){
            if(!is_file(self::$server_file))file_put_contents(self::$server_file,'');
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

        if ($run && Config::getField('project', 'debug_mode', 0)) {
            Debug::end();
        }
    }

    protected static function stop(){
        if(empty(self::$server_pid)){
            echo ("Service has shut down!\n");
        }else{
            $res = self::getOs()->kill(self::$server_pid, SIGTERM);
            if($res ) {
                self::$server_pid = 0;
            }
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
        global $argv;
        if(PHP_OS == 'Linux'){
            $grepName = self::$appName;
        }else{
            $grepName = $argv[0];
        }
        exec('ps axu|grep '.$grepName, $output);
        $output = self::packExeData($output);
        $pidDetail = SwoolePid::getPidList(self::$server_file);
        $pidList = [];
        foreach($pidDetail as $key => $value){
            if(is_array($value)){
                foreach($value as $k => $v){
                    if($v==1) {
                        $pidList[$k] = ['type'=>$key];
                    }
                }
            }else{
                $pidList[$value] = ['type'=>$key];
            }
        }
        $pidDetail = [];
        foreach($output as $key => $value){
            if(!empty($pidList[$value[1]])){
                $value[] = $pidList[$value[1]]['type'];
                $pidDetail[] = $value;
            }
        }
        self::outputStatus($pidDetail);
    }

    protected static function outputStatus($pidDetail){
        echo "Welcome ".self::$appName."!\n";
        $pidStatic = [];
        foreach($pidDetail as $key => $value){
            if(empty($pidStatic[$value[11]])){
                $pidStatic[$value[11]] = 1;
            }else{
                $pidStatic[$value[11]] ++;
            }
        }
        foreach($pidStatic as $key => $value){
            echo ucfirst($key)." Process Num:".$value."\n";
        }

        echo "-------------PROCESS STATUS--------------\n";
        echo "Type    Pid   %CPU  %MEM   MEM     Start \n";
        foreach($pidDetail as $key => $value){
            echo str_pad($value[11],8).str_pad($value[1],6).str_pad($value[2],6).
                str_pad($value[3],7).str_pad(round($value[5]/1024,2)."M",8).$value[8]."\n";
        }

    }

    protected static function packExeData($output){
        $data = [];
        foreach($output as $key => $value){
            $data[] = self::dealSingleData($value);
        }
        return $data;
    }

    protected static function dealSingleData($info){
        $data = [];
        $i = 0;
        $num = 0;

        while($num<=9) {
            $start = '';
            while ($info[$i] != ' ') {
                $start .= $info[$i];
                $i++;
            }
            $data[] = $start;
            while ($info[$i] == ' ') {
                $i++;
            }
            $num++;
        }
        $data[] = substr($info, $i);
        return $data;
    }

}
