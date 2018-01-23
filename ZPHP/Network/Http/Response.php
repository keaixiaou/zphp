<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/4/14
 * Time: 下午2:39
 */

namespace ZPHP\Network\Http;

use ZPHP\Core\Config;
use ZPHP\Core\Rand;
use ZPHP\Network\BaseResponse;
use ZPHP\Session\Session;

class Response extends BaseResponse{
    const HTTP_CONTINUE = 100;
    const HTTP_SWITCHING_PROTOCOLS = 101;
    const HTTP_PROCESSING = 102;            // RFC2518
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_ACCEPTED = 202;
    const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    const HTTP_NO_CONTENT = 204;
    const HTTP_RESET_CONTENT = 205;
    const HTTP_PARTIAL_CONTENT = 206;
    const HTTP_MULTI_STATUS = 207;          // RFC4918
    const HTTP_ALREADY_REPORTED = 208;      // RFC5842
    const HTTP_IM_USED = 226;               // RFC3229
    const HTTP_MULTIPLE_CHOICES = 300;
    const HTTP_MOVED_PERMANENTLY = 301;
    const HTTP_FOUND = 302;
    const HTTP_SEE_OTHER = 303;
    const HTTP_NOT_MODIFIED = 304;
    const HTTP_USE_PROXY = 305;
    const HTTP_RESERVED = 306;
    const HTTP_TEMPORARY_REDIRECT = 307;
    const HTTP_PERMANENTLY_REDIRECT = 308;  // RFC7238
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_PAYMENT_REQUIRED = 402;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_NOT_ACCEPTABLE = 406;
    const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    const HTTP_REQUEST_TIMEOUT = 408;
    const HTTP_CONFLICT = 409;
    const HTTP_GONE = 410;
    const HTTP_LENGTH_REQUIRED = 411;
    const HTTP_PRECONDITION_FAILED = 412;
    const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    const HTTP_REQUEST_URI_TOO_LONG = 414;
    const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const HTTP_EXPECTATION_FAILED = 417;
    const HTTP_I_AM_A_TEAPOT = 418;                                               // RFC2324
    const HTTP_UNPROCESSABLE_ENTITY = 422;                                        // RFC4918
    const HTTP_LOCKED = 423;                                                      // RFC4918
    const HTTP_FAILED_DEPENDENCY = 424;                                           // RFC4918
    const HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL = 425;   // RFC2817
    const HTTP_UPGRADE_REQUIRED = 426;                                            // RFC2817
    const HTTP_PRECONDITION_REQUIRED = 428;                                       // RFC6585
    const HTTP_TOO_MANY_REQUESTS = 429;                                           // RFC6585
    const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;                             // RFC6585
    const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_NOT_IMPLEMENTED = 501;
    const HTTP_BAD_GATEWAY = 502;
    const HTTP_SERVICE_UNAVAILABLE = 503;
    const HTTP_GATEWAY_TIMEOUT = 504;
    const HTTP_VERSION_NOT_SUPPORTED = 505;
    const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;                        // RFC2295
    const HTTP_INSUFFICIENT_STORAGE = 507;                                        // RFC4918
    const HTTP_LOOP_DETECTED = 508;                                               // RFC5842
    const HTTP_NOT_EXTENDED = 510;                                                // RFC2774
    const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;

    private $header = ['Connection'=>'keep-alive'];

    private $cookie = [];
    private $session = [];
    private $code = self::HTTP_OK;
    private $swResponse;

    protected function setTypeVal($type, $key, $value){
        if(is_null($value))
            unset($this->$type[$key]);
        else
            $this->$type[$key] = $value;
    }

    public function setHeader($header){
        $this->header = $header;
    }

    public function setHeaderVal($key, $value=null){
        $this->setTypeVal('header', $key, $value);
    }

    public function setCookie($cookie){
        $this->cookie = $cookie;
    }

    public function getCookie(){
        return $this->cookie;
    }

    public function setCookieVal($key, $value=null){
        $this->setTypeVal('cookie', $key, $value);
    }


    public function setSession($session){
        $this->session = $session;
    }

    public function setSessionVal($key, $value){
        $this->setTypeVal('session', $key, $value);
    }

    public function setHttpCode($code){
        $this->code = $code;
    }

    public function getSession(){
        return $this->session;
    }

    protected function responseArrayVal($type, $cacheTime=0){
        foreach($this->$type as $key => $value){
            if(empty($cacheTime))
                $this->swResponse->$type($key, $value);
            else
                $this->swResponse->$type($key, $value, time()+$cacheTime);
        }
    }


    protected function responseHeader(){
        $this->responseArrayVal('header');
    }

    protected function responseSession(){

        if(!empty(Config::getField('session', 'enable'))){
            $sid = null;
            if(!empty($this->cookie[Session::$_sessionKey]))
                $sid = $this->cookie[Session::$_sessionKey];
            if(empty($sid)){
                $sid = Rand::string(8);
                $this->cookie[Session::$_sessionKey] = $sid;
            }
            yield Session::set($this->session, $sid);
        }
        $this->responseCookie();
    }

    protected function responseCookie(){
        if(!empty($this->cookie)) {
            $this->responseArrayVal('cookie');
        }
    }

    public function finish($swResponse){
        $this->swResponse = $swResponse;
        $this->responseHeader();
        yield $this->responseSession();
        $this->swResponse->status($this->code);
        $this->swResponse->end($this->content);
    }
}