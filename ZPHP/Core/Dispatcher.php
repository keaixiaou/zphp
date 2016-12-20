<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/29
 * Time: 下午5:14
 */

namespace ZPHP\Core;

class Dispatcher{

    public function __construct()
    {
    }


    public function distribute(Request $requestDeal)
    {
        $mvc = $requestDeal->parse();
        $httpResult = null;
        if(!empty($mvc['callback'])){
            $httpResult = $requestDeal->callbackDistribute($mvc['callback'], $mvc['param']);
        }else{
            $httpResult = $requestDeal->defaultDistribute($mvc['mvc']);
        }
        return $httpResult;
    }





}