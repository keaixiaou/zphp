<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/29
 * Time: 下午5:14
 */

namespace ZPHP\Core;

class Dispatcher{

    protected $requestDeal;
    public function __construct()
    {
    }


    public function distribute(Request $requestDeal)
    {
        $this->requestDeal = $requestDeal;
        $mvc = $this->requestDeal->parse();
        $httpResult = null;
        if(!empty($mvc['callback'])){
            $httpResult = $this->requestDeal->callbackDistribute($mvc['callback'], $mvc['param']);
        }else{
            $httpResult = $this->requestDeal->defaultDistribute($mvc['mvc']);
        }
        return $httpResult;
    }





}