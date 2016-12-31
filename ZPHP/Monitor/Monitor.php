<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/30
 * Time: 上午9:26
 */

namespace ZPHP\Monitor;

use ZPHP\Client\SwoolePid;
use ZPHP\Core\Config;

class Monitor {

    protected $monitorname;
    protected $filename;

    function __construct($param)
    {
        $this->monitorname = $param[0];
        $this->filename = $param[1];
    }


    public function outPutWebStatus(){
        $this->outputStatus($this->getNowStatus(), '<br/>');
    }

    public function outPutNowStatus(){
        $this->outputStatus($this->getNowStatus());
    }

    public function getNowStatus(){
        exec('ps axu|grep '.$this->monitorname, $output);
        $output = $this->packExeData($output);
        $pidDetail = SwoolePid::getPidList($this->filename);
        $pidList = [];
        foreach($pidDetail as $key => $value){
            if(is_array($value)){
                foreach($value as $k => $v){
                    if($v==1) {
                        $pidList[$k] = ['type'=>$key];
                    }
                }
            }else{
                $pidList[$value] = ['type'=>$key];
            }
        }
        $pidDetail = [];
        foreach($output as $key => $value){
            if(!empty($pidList[$value[1]])){
                $value[] = $pidList[$value[1]]['type'];
                $pidDetail[] = $value;
            }
        }
        return $pidDetail;
    }


    protected function outputStatus($pidDetail, $explode="\n"){
        echo "Welcome ".Config::get('project_name')."!".$explode;
        $pidStatic = [];
        foreach($pidDetail as $key => $value){
            if(empty($pidStatic[$value[11]])){
                $pidStatic[$value[11]] = 1;
            }else{
                $pidStatic[$value[11]] ++;
            }
        }
        foreach($pidStatic as $key => $value){
            echo ucfirst($key)." Process Num:".$value.$explode;
        }

        echo "-------------PROCESS STATUS--------------".$explode;
        echo "Type    Pid   %CPU  %MEM   MEM     Start ".$explode;
        foreach($pidDetail as $key => $value){
            echo str_pad($value[11],8).str_pad($value[1],6).str_pad($value[2],6).
                str_pad($value[3],7).str_pad(round($value[5]/1024,2)."M",8).$value[8].$explode;
        }

    }

    protected function packExeData($output){
        $data = [];
        foreach($output as $key => $value){
            $data[] = $this->dealSingleData($value);
        }
        return $data;
    }

    protected function dealSingleData($info){
        $data = [];
        $i = 0;
        $num = 0;

        while($num<=9) {
            $start = '';
            while ($info[$i] != ' ') {
                $start .= $info[$i];
                $i++;
            }
            $data[] = $start;
            while ($info[$i] == ' ') {
                $i++;
            }
            $num++;
        }
        $data[] = substr($info, $i);
        return $data;
    }
}