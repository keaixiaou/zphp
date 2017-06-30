<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2017/3/24
 * Time: 下午2:36
 */

namespace ZPHP\Common;

class DocParser {
    private $params = array ();
    public function parse($doc = '') {
        if ($doc == '') {
            return $this->getParseRes();
        }
        // Get the comment
        if (preg_match ( '#^/\*\*(.*)\*/#s', $doc, $comment ) === false)
            return $this->getParseRes();
        $comment = trim ( $comment [1] );
        // Get all the lines and strip the * from the first character
        if (preg_match_all ( '#^\s*\*(.*)#m', $comment, $lines ) === false)
            return $this->getParseRes();
        $this->parseLines ( $lines [1] );
        return $this->getParseRes();
    }

    protected function getParseRes(){
        $param = $this->params;
        $this->params = [];
        return $param;
    }


    protected function exportDocHtml($temFile, $val, $htmlFileName){
        ob_start();
        extract($val, EXTR_OVERWRITE);
        include "$temFile";
        $outPut = ob_get_clean();
        $fileName = $htmlFileName;
        $filePath = dirname($fileName);
        if(!is_dir($filePath)){
            mkdir($filePath, 0755, true);
        }
        file_put_contents($fileName, $outPut);
        if(ob_get_contents())ob_end_clean();
    }


    /**
     * 解析return返回值注释
     * @param $return
     * @return array
     */
    public function parseReturn($return){
        $data = [];
        $i = 0;
        $level = 0;
        $nextVal = [];
        foreach($return as $key => $value){
            if($i>0){
                $nowLevel = substr_count($value[0], '_');
                if($nowLevel>$level){
                    $nextVal[] = $value;
                }else{
                    if(!empty($nextVal)) {
                        $nextExample = $this->parseReturn($nextVal);
                        if(strtolower(str_replace('_', '', $lastVal[0]))=='array'){
                            $nextExample = [$nextExample];
                        };
                        $data[$lastVal[1]] = $nextExample;
                        $nextVal = [];
                    }
                    $lastVal = $value;
                    $data[$value[1]] = $this->getDefaultVal($value[0]);
                }
            }else{
                $lastVal = $value;
                $level = substr_count($value[0], '_');
                $data[$value[1]] = $this->getDefaultVal($value[0]);
            }

            $i ++;
        }
        if(!empty($nextVal)) {

            $nextExample = $this->parseReturn($nextVal);
            if(strtolower(str_replace('_', '', $lastVal[0]))=='array'){
                $nextExample = [$nextExample];
            };
            $data[$lastVal[1]] = $nextExample;
            $nextVal = [];
        }
        return $data;
    }

    /**
     * 生成返回值默认值
     * @param $type
     * @param null $data
     * @return array|float|int|string
     */
    protected function getDefaultVal($type){
        $type = strtolower(str_replace('_', '', $type));
        switch($type){
            case 'int':
                $val = 0;
                break;
            case 'float':
                $val = 0.00;
                break;
            case 'string':
                $val = "string";
                break;
            case 'array':
                $val = [];
                break;
            case 'object':
                $val = [];
                break;
            default:
                $val = "";
                break;
        }
        return $val;
    }


    /**
     * 生成api接口文档
     * @param $filePath
     * @param $filter
     * @param $docPath
     * @throws \Exception
     */
    public function makeDocHtml($filePath, $filter, $docPath)
    {
        $result = Dir::getFileName($filePath, '/.php$/');
        $classList = [];
        foreach($result as $key => $value){
            $file = $filePath.DS.$value;
            $content = '<<<PHP_CODE'.file_get_contents($file).'PHP_CODE';
            $code = token_get_all($content);
            $classList[] = $this->parsePhpCode($code, $filter);
        }

        $allDorArray = [];
        foreach($classList as $key => $value){
            foreach($value['function'] as $k => $v){
                $v['namespace'] = $value['namespace'];
                $v['classname'] = $value['classname'];
                $allDorArray[$v['filename']] = $v;
            }
        }
        $temPath = $docPath.DS.'template';
        $temFile = $temPath.DS.'detail.html';
        $markdown = $temPath.DS.'markdown.html';
        $htmlPath = $docPath.DS.'html';
        $markdownPath = $docPath.DS.'markdown';
        foreach($allDorArray as $ky => $val){
            $this->exportDocHtml($temFile, $val, $htmlPath.DS.$val['html_url']);
            $this->exportDocHtml($markdown, $val, $markdownPath.DS.$val['markdown']);
        }

        $indexTemFile = $temPath.DS.'index.html';
        $fileName = $htmlPath.DS.'index.html';
        $this->exportDocHtml($indexTemFile,['classList'=>$classList] , $fileName);
    }

    private function parsePhpCode($code, $filter=''){
        $i =0 ;
        $namespace = '\\';
        $className = '';
        $classInfo = [];
        $startNameSpace = false;
        foreach($code as $c){

            if($startNameSpace){
                if(is_array($c)&& !empty($c[1])){
                    if(!empty($c[1][0]) && $c[1][0]!=' ')
                        $namespace .= $c[1];
                }else{
                    $classInfo['namespace'] = $namespace;
                    $startNameSpace = false;
                }
            }

            if(is_array($c) && !empty($c[1]) && $c[1]=='namespace'){
                $startNameSpace = true;
            }

            if(is_array($c) && !empty($c[1]) && $c[1]=='class'){
                $className = $code[$i+2][1];
                $classInfo['classname'] = ($namespace=='\\'?'':$namespace).'\\'.$className;
                if(!empty($filter)) $classInfo['classname'] = str_replace($filter,'', $classInfo['classname']);
            }
            if(is_array($c) && !empty($c[1]) && $c[1]=='function'){
                if(is_array($code[$i-2]) && !empty($code[$i-2][1]) && strtolower($code[$i-2][1])=='public'){
                    $info = [];
                    if(is_array($code[$i-4]) && !empty($code[$i-4][1]) && $code[$i-4][1][0]=='/'){
                        $docInfo = $code[$i-4][1];
                        $info = $this->parse($docInfo);
                        if(!empty($info['return'])){
                            $info['example'] = json_encode($this->parseReturn($info['return']), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
                            foreach($info['return'] as $k => $v){
                                $info['return'][$k][1] = str_repeat('_', substr_count($v[0], '_')).$v[1];
                                $info['return'][$k][0] = str_replace('_','', $v[0]);
                            }
                        }
                    }
                    $info['name'] = $code[$i+2][1];

                    $info['url'] = !empty($info['url'])?$info['url']:str_replace("\\",'/', $classInfo['classname']).'/'.$info['name'];
                    $info['filename'] = str_replace("/",'_', $info['url']);
                    $info['html_url'] = $info['filename'].'.html';
                    $info['markdown'] = $info['filename'].'.md';
                    $classInfo['function'][] = $info;
                }
            }
            $i ++;
        }
        return $classInfo;
    }

    private function parseLines($lines) {
        foreach ( $lines as $line ) {
            $parsedLine = $this->parseLine ( $line ); // Parse the line

            if ($parsedLine === false && ! isset ( $this->params ['description'] )) {
                if (isset ( $desc )) {
                    // Store the first line in the short description
                    $this->params ['description'] = implode ( PHP_EOL, $desc );
                }
                $desc = array ();
            } elseif ($parsedLine !== false) {
                $desc [] = $parsedLine; // Store the line in the long description
            }
        }

        if (! empty ( $desc )){
            $desc = implode ( ' ', $desc );
            $this->params ['long_description'] = $desc;
        }

    }
    private function parseLine($line) {
        // trim the whitespace from the line
        $line = trim ( $line );

        if (empty ( $line ))
            return false; // Empty line

        if (strpos ( $line, '@' ) === 0) {
            $param = null;
            $values = [];
            $explodeArray = explode(' ', substr($line, 1));
            $isParam = false;
            foreach($explodeArray as $key => $value){
                if(!empty($value)){
                    if(!$isParam){
                        $param = $value;
                        $isParam = true;
                    }else{
                        $values[] = str_replace('$', '',$value);
                    }

                }
            }

            // Parse the line and return false if the parameter is valid
            if (!empty($param && $this->setParam ( $param, $values )))
                return false;
        }

        return $line;
    }
    private function setParam($param, $value) {
        if ($param == 'param' || $param == 'return'){
            $this->params[$param][] = $value;
        }else{
            $this->params[$param] = $value[0];
        }
        return true;
    }
}