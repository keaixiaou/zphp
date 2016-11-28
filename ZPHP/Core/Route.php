<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/28
 * Time: 下午3:43
 */


namespace ZPHP\Core;

class Route {

    static protected $routeList = [];
    static public function init(){

    }

    /**
     * 路由解析
     * @param $uri
     * @param $method
     * @return array|null
     * @throws \Exception
     */
    static public function parse($uri, $method){
        $routeConfig = Config::get('route');
        $match = false;
        $uriResult = null;
        if(!empty($routeConfig[$method][$uri])){
            $match = true;
            $uriResult = $routeConfig[$method][$uri];
            if(is_string($uriResult)){
                $explodeNum = substr_count($uriResult, '\\');
                if($explodeNum>=1){
                    if($explodeNum==1){
                        $mvcConfig = Config::getField('project','mvc');
                        $mvc = explode('\\', $uriResult);
                        $uriResult = [
                            'module' => $mvcConfig[0],
                            'controller' => $mvc[0],
                            'action' => $mvc[1],
                        ];
                    }else{
                        $mvc = explode('\\', $uriResult);
                        $uriResult = [
                            'module' => $mvc[0],
                            'controller' => $mvc[1],
                            'action' => $mvc[2],
                        ];
                    }
                }
            }
        }
        if(!$match){
            $uriResult = self::defaultParse($uri);
        }
        return $uriResult;
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
            throw new \Exception(402);
        }else{
            if(!empty($url_array[2])){
                $mvc['module'] = $url_array[0];
                $mvc['controller'] = $url_array[1];
                $mvc['action'] = $url_array[2];
            }else if(!empty($url_array[1])){
                $mvc['controller'] = $url_array[0];
                $mvc['action'] = $url_array[1];
            }else if(!empty($url_array[0])){
                $mvc['action'] = $url_array[0];
            }
        }
        $mvc = self::dealUcword($mvc);
        return $mvc;
    }


    /**
     * 首字母大写
     * @param $mvc
     * @return array
     */
    static function dealUcword($mvc){
        return [
            'module'=>ucwords($mvc['module']),
            'controller'=>ucwords($mvc['controller']),
            'action'=>$mvc['action'],
        ];
    }
}