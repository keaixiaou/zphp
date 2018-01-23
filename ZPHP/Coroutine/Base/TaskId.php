<?php
/**
 * Created by PhpStorm.
 * author: zhaoye(zhaoye@youzan.com)
 * Date: 2018/1/19
 * Time: 下午3:55
 */
namespace ZPHP\Coroutine\Base;

class TaskId{
    private static $id = 0;

    public static function create()
    {
        if (self::$id >= PHP_INT_MAX) {
            self::$id = 1;
            return self::$id;
        }

        self::$id++;
        return self::$id;
    }
}