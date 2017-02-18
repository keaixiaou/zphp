<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/9/21
 * Time: 下午6:54
 */


namespace ZPHP\Coroutine\Http;

use ZPHP\Coroutine\Base\CoroutineBase;

class HttpClientCoroutine extends CoroutineBase{
    public function __construct(){
        $this->ioVector = new Client();
    }


    /**
     * @param $url
     * @param array $postData
     * @return $this
     */
    public function request($url, $postData=[]){
        $data = ['url'=>$url, 'postdata'=>$postData];
        return parent::command($data);
    }

}