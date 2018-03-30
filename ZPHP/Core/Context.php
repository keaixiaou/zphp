<?php
/**
 * Created by PhpStorm.
 * author: zhaoye(zhaoye@youzan.com)
 * Date: 2018/1/22
 * Time: 下午5:14
 */

namespace ZPHP\Core;

class Context{
    private $map = [];
    public function __construct($content=[])
    {
        foreach ($content as $key => $value){
            $this->set($key, $value);
        }
    }


    public function get($key, $default = null)
    {
        $default = isset($this->map[$key])?$this->map[$key]:$default;
        return $default;
    }

    public function set($key, $value)
    {
        $this->map[$key] = $value;
        return true;
    }

    public function getAll()
    {
        return $this->map;
    }

}