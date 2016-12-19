<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/19
 * Time: 下午7:54
 */

namespace ZPHP\View;

class View{
    public function fetch($vVar, $vFile){
        ob_start();
        extract($vVar);
        if(!is_file($vFile)){
            throw new \Exception("模板不存在.");
        }
        include "{$vFile}";
        $outPut = ob_get_contents();
        return $outPut;
    }

}