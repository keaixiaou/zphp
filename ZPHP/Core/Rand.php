<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/11/14
 * Time: 下午1:37
 */

namespace ZPHP\Core;

class Rand{
    /**
     * 随机生成一个字符串
     * @param $length
     * @param $number
     * @param $not_o0
     * @return string
     */
    static function string($length = 8, $number = true, $not_o0 = false)
    {
        $strings = 'ABCDEFGHIJKLOMNOPQRSTUVWXYZ';  //字符池
        $numbers = '0123456789';                    //数字池
        if ($not_o0)
        {
            $strings = str_replace('O', '', $strings);
            $numbers = str_replace('0', '', $numbers);
        }
        $pattern = $strings . $number;
        $max = strlen($pattern) - 1;
        $key = '';
        for ($i = 0; $i < $length; $i++)
        {
            $key .= $pattern{mt_rand(0, $max)};    //生成php随机数
        }
        return $key;
    }
}