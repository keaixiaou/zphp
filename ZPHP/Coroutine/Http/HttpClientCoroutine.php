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
    protected $carrier = "http";
    public function __construct(){
        $this->ioVector = new Client();
    }

    public function getParam(){
        if(!empty($this->data["postdata"])){
            $param = $this->data["url"] ." ".json_encode($this->data["postdata"]);
        }else{
            $param = $this->data["url"];
        }
        return $param;
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