<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/19
 * Time: 下午7:54
 */

namespace ZPHP\View;

use ZPHP\Template\File;
use ZPHP\Template\ViewCache;
use ZPHP\ZPHP;

class View{
    protected $config;
    protected $template;
    protected $tplVar = [];
    protected $tplFile = '';
    protected $tmodule ;
    protected $tcontroller;
    protected $tmethod;
    protected $tplPath;
    protected $originPath;

    function __construct($config)
    {
        $this->config = $config;
        //使用引擎
        if(!empty($config['engine'])){
            $this->tplPath = ZPHP::getTmpPath();
        }else{
            //使用原声php
            $this->tplPath = ZPHP::getAppPath();
        }
        $this->tplPath = $this->tplPath . DS . 'view' . DS;
        $this->originPath = ZPHP::getAppPath(). DS . 'view' . DS;
    }


    /**
     * @param $mvc ['module'=>,'controller'=>,'method'=>]
     */
    public function init($mvc){
        $this->tmodule = $mvc['module'];
        $this->tcontroller = $mvc['controller'];
        $this->tmethod = $mvc['method'];
    }

    /**
     * 指定模板文件
     * @param $tplFile
     * @throws \Exception
     */
    public function analysisTplFile(){
        if(!empty($this->tplFile)){
            $tplExplode = explode('/', trim($this->tplFile,'/'));
            $tplCount = count($tplExplode);
            if($tplCount>3) {
                throw new \Exception("模板文件目录有误");
            }else if($tplCount==1){
                if(!empty($tplExplode[0])){
                    $this->tmethod = $tplExplode[0];
                }
            }else if($tplCount==2){
                if(!empty($tplExplode[0])){
                    $this->tcontroller = $tplExplode[0];
                }
                if(!empty($tplExplode[1])){
                    $this->tmethod = $tplExplode[1];
                }
            }else{
                if(!empty($tplExplode[0])){
                    $this->tmodule = $tplExplode[0];
                }
                if(!empty($tplExplode[1])){
                    $this->tcontroller = $tplExplode[1];
                }
                if(!empty($tplExplode[2])){
                    $this->tmethod = $tplExplode[2];
                }
            }
        }
    }


    public function setTemplate($template){
        $orginTemplateFile = 'Template'.DS.$template.'.html';
        if(!is_file($this->originPath.$orginTemplateFile)){
            throw new \Exception("模板不存在!");
        }
        $this->template = $orginTemplateFile;
    }


    public function setViewFile($file){
        $this->tplFile = $file;
    }
    /**
     * 获取真正的view文件
     * @return string
     * @throws \Exception
     */
    public function getRealFile(){
        $this->analysisTplFile();
        $tplFile = $this->tmodule.DS.$this->tcontroller.DS.$this->tmethod.'.html';
        $checkFile = [$tplFile];
        $outFile = $tplFile;
        if(!empty($this->template)){
            $checkFile[] = $this->template;
            $outFile = $this->template;
            $this->tplVar['template_content'] =  $this->tplPath.$tplFile;
        }
        if(!empty($this->config['engine'])) {
            ViewCache::checkCache($checkFile);
        }
        return $this->tplPath.$outFile;
    }

    public function fetch($vVar, $file){
        $this->setViewFile($file);
        $vFile = $this->getRealFile();
        ob_start();
        $vVar = array_merge($this->tplVar, $vVar);
        extract($vVar);
        if(!is_file($vFile)){
            throw new \Exception("模板不存在.");
        }
        include "{$vFile}";
        $outPut = ob_get_contents();
        return $outPut;
    }

}