# zphp
框架依赖


#####路由默认 配置：  
```
'project'=>array('name'=>'zhttp',
        'view'=> [
            'tag'=>false,
        ],
        'pid_path'  => ROOTPATH.'/webroot',
        'mvc'  => [
            'app'=>'app','module'=>'Home', 'controller' => 'Index', 'action' => 'index'
        ],
        'reload' => DEBUG,
)
```

    
#####路由规则：
```
ip:port/$app/$module/$controller/$action
ip:port/$module/$controller/$action
ip:port/$controller/$action
ip:port/$controller
```
**其余都读取配置文件中的参数**

#####service调用方式
```
App::service("App#test")->test();
```
#####model调用方式
```
App::model("App#test")->test();
```
#####多个数据库配置：

```
'mysql' => array(
        'default' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'root',
            'password' => '******',
            'database' => 'zhttp',
            'asyn_max_count' => 2,
            'start_count' => 2,
        ],
        'read' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'root',
            'password' => '******',
            'database' => 'zhttp',
            'asyn_max_count' => 5,
            'start_count' => 5,
        ]
    )
```
#####数据库操作方式
```
Db::table("#DbName$tableName")
```