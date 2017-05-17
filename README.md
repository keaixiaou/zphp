# zphp
框架依赖


路由默认 配置：

'project'=>array(
        'name'=>'zhttp',
        'view'=> [
            'tag'=>false,
        ],
        'pid_path'  => ROOTPATH.'/webroot',
        'mvc'  => [
            'app'=>'app','module'=>'Home', 'controller' => 'Index', 'action' => 'index'
        ],
        'reload' => DEBUG,
    )
路由规则：

ip:port/$app/$module/$controller/$action
ip:port/$module/$controller/$action
ip:port/$controller/$action
ip:port/$controller

其余都读取配置文件中的参数

service调用方式
App::service("App#test")->test();
model调用方式
App::model("App#test")->test();