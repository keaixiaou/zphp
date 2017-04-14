<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/4/14
 * Time: 下午3:53
 */

namespace ZPHP\Network;

use ZPHP\Core\Log;

class BaseResponse{

    public $content = '';

    public $isApi=false;
    protected $hasResponse=false;

    public function setReponseContent($responseContent){
        $this->content = $responseContent;
        $this->endResponse();
    }


    public function endResponse(){
        if($this->hasResponse===true)
            Log::write("ResponseData has been set!", Log::WARN, true);
        $this->hasResponse = true;
    }

    public function checkResponse(){
        return $this->hasResponse===true?false:true;
    }

    public function setApi(){
        $this->isApi = true;
    }

    public function checkApi(){
        return $this->isApi;
    }


}