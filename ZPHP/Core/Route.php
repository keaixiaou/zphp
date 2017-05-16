<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/28
 * Time: 下午3:43
 */


namespace ZPHP\Core;

class Route {
    static public $matchRouteList=[];
    static public $routeList = [];
    static public function init(){
        $routeConfig = Config::get('route');
        if(!empty($routeConfig)) {
            foreach ($routeConfig as $key => $value) {
                $method = strtoupper($key);
                foreach ($value as $k => $v) {
                    $routeK = $k;
                    if (is_string($v)) {
                        $v = trim($v, '\\');
                    }
                    if (preg_match_all('/(.*?)\/{.*?}/', $k, $match)) {
                        $routeK = str_replace('{', '(?P<', $routeK);
                        $routeK = str_replace('}', '>[^/]++)', $routeK);
                        $routeK = '#^' . $routeK . '$#';
                        if ($method == 'ANY') {
                            self::$matchRouteList['POST'][$routeK] = $v;
                            self::$matchRouteList['GET'][$routeK] = $v;
                        } else {
                            self::$matchRouteList[$method][$routeK] = $v;
                        }
                    } else {
                        if ($method == 'ANY') {
                            self::$routeList['POST'][$routeK] = $v;
                            self::$routeList['GET'][$routeK] = $v;
                        } else {
                            self::$routeList[$method][$routeK] = $v;
                        }
                    }

                }
            }
        }
    }

    /**
     * 路由解析
     * @param $uri
     * @param $method
     * @return array|null
     * @throws \Exception
     */
    static public function parse($uri, $method){
        $uriResult = self::routeParse($uri, $method);
        if(empty($uriResult)){
            $uriResult = self::defaultParse($uri);
        }
        return $uriResult;
    }

    /**
     * 配置文件的路由解析
     * @param $uri
     * @param $method
     * @throws \Exception
     */
    static public function routeParse($uri, $method){
        $method = strtoupper($method);
        $uriResult = null;
        if(!empty(self::$routeList[$method][$uri])){
            $uriResult = self::$routeList[$method][$uri];
            if(is_string($uriResult)){
                $uriResult = self::parseString($uriResult);
            }else if($uriResult instanceof \Closure){
                $uriResult = ['callback'=>$uriResult];
            }
        }else if(!empty(self::$matchRouteList[$method])){
            $methodMatchList = self::$matchRouteList[$method];
            foreach($methodMatchList as $key => $value){
                if(preg_match($key, $uri, $tmpMatch)){
                    if(is_string($value))$uriResult = self::parseString($value);
                    else $uriResult = ['callback'=>$value,'param'=>$tmpMatch];
                }
            }

        }
        return $uriResult;
    }

    /**
     * 解析路由里字符串
     * @param $str
     * @return array
     * @throws \Exception
     */
    protected function parseString($str){
        $mvc = explode('\\', $str);
        $explodeNum = count($mvc)-1;
        if($explodeNum>=1){
            if($explodeNum==1){
                $mvcConfig = Config::getField('project','mvc');
                $uriResult = [
                    'app' => $mvcConfig['app'],
                    'module' => $mvcConfig['module'],
                    'controller' => $mvc[0],
                    'action' => $mvc[1],
                ];
            }elseif($explodeNum == 2)  {
                $mvcConfig = Config::getField('project','mvc');
                $uriResult = [
                    'app' => $mvcConfig['app'],
                    'module' => $mvc[0],
                    'controller' => $mvc[1],
                    'action' => $mvc[2],
                ];
            }else{
                $uriResult = [
                    'app' => $mvc[0],
                    'module' => $mvc[1],
                    'controller' => $mvc[2],
                    'action' => $mvc[3],
                ];
            }
        }
        return ['mvc'=>self::dealUcfirst($uriResult)];
    }

    /**
     * 默认解析路由
     * @param $uri
     * @return array
     * @throws \Exception
     */
    static public function defaultParse($uri){
        $mvc = Config::getField('project','mvc');
        $url_array = explode('/', trim($uri,'/'));
        if(!empty($url_array[3])){
            $mvc['app'] = $url_array[0];
            $mvc['module'] = $url_array[1];
            $mvc['controller'] = $url_array[2];
            $mvc['action'] = $url_array[3];
        }else if(!empty($url_array[2])){
            $mvc['module'] = $url_array[0];
            $mvc['controller'] = $url_array[1];
            $mvc['action'] = $url_array[2];
        }else if(!empty($url_array[1])){
            $mvc['controller'] = $url_array[0];
            $mvc['action'] = $url_array[1];
        }else if(!empty($url_array[0])){
            $mvc['module'] = $url_array[0];
        }
        $mvc = [ 'mvc'=> self::dealUcfirst($mvc)];
        return $mvc;
    }


    /**
     * 首字母大写
     * @param $mvc
     * @return array
     */
    static function dealUcfirst($mvc){
        return [
            'app'=>ucwords($mvc['app']),
            'module'=>ucwords($mvc['module']),
            'controller'=>ucwords($mvc['controller']),
            'action'=>$mvc['action'],
        ];
    }
}