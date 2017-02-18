<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/2/18
 * Time: 下午3:31
 */

namespace ZPHP\Coroutine\Base;

interface IOvector{
    function command(callable $callback, $data);
}
