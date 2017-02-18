<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/23
 * Time: 下午11:52
 */

namespace ZPHP\Mongo;


use ZPHP\Core\Log;
use ZPHP\Coroutine\Mongo\MongoCoroutine;

class Mongo{
    /**
     * @var MongoCoroutine
     */
    public $mongoAsynPool;
    protected $filter=[];
    protected $options=[];
    protected $collection;
    protected $comparison = ['neq'=>'ne','ne'=>'ne','gt'=>'gt',
        'egt'=>'gte','gte'=>'gte','lt'=>'lt','elt'=>'lte',
        'lte'=>'lte','in'=>'in','not in'=>'nin','nin'=>'nin'];
//    protected
    function __construct($collection, $mongoAsynPool)
    {
        $this->collection = $collection;
        $this->mongoAsynPool = $mongoAsynPool;
    }

    /**
     * @param $where
     * @return $this
     * @throws \Exception
     */
    public function where($where){
        if(!is_array($where)){
            throw new \Exception('查询条件必须为Array');
        }
        foreach($where as $key => $val){
            $filter = $this->parseWhereItem($key,$val);
            $this->filter = array_merge($this->filter, $filter);
        }
        return $this;
    }


    /**
     * where子单元分析
     * @access protected
     * @param string $key
     * @param mixed $val
     * @return array
     */
    protected function parseWhereItem($key,$val) {
        $query   = array();
        if(is_array($val)) {
            if(is_string($val[0])) {
                $con  =  strtolower($val[0]);
                if(in_array($con,array('neq','ne','gt','egt','gte','lt','lte','elt'))) { // 比较运算
                    $k = '$'.$this->comparison[$con];
                    $query[$key]  =  array($k=>$val[1]);
                }elseif('mod'==$con){ // mod 查询
                    $query[$key]   =  array('$mod'=>$val[1]);
                }elseif(in_array($con,array('in','nin','not in'))){ // IN NIN 运算
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $k = '$'.$this->comparison[$con];
                    $query[$key]  =  array($k=>$data);
                }elseif('all'==$con){ // 满足所有指定条件
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $query[$key]  =  array('$all'=>$data);
                }elseif('between'==$con){ // BETWEEN运算
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $query[$key]  =  array('$gte'=>$data[0],'$lte'=>$data[1]);
                }elseif('not between'==$con){
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $query[$key]  =  array('$lt'=>$data[0],'$gt'=>$data[1]);
                }elseif('exists'==$con){ // 字段是否存在
                    $query[$key]  = array('$exists'=>(bool)$val[1]);
                }elseif('size'==$con){ // 限制属性大小
                    $query[$key]  = array('$size'=>intval($val[1]));
                }elseif('type'==$con){ // 限制字段类型 1 浮点型 2 字符型 3 对象或者MongoDBRef 5 MongoBinData 7 MongoId 8 布尔型 9 MongoDate 10 NULL 15 MongoCode 16 32位整型 17 MongoTimestamp 18 MongoInt64 如果是数组的话判断元素的类型
                    $query[$key]  = array('$type'=>intval($val[1]));
                }else{
                    $query[$key]  =  $val;
                }
                return $query;
            }
        }
        $query[$key]  =  $val;
        return $query;
    }

    /**
     * @param $orderStr
     * @return $this
     */
    public function order($orderStr){
        $orderStr = trim($orderStr);
        $sortArray = explode(',', $orderStr);
        foreach($sortArray as $key => $value){
            $value = trim($value);
            $space = strpos($value, ' ');
            if(empty($space)){
                $this->options['sort'][$value] = 1;
            }else{
                $k = substr($value,0,$space);
                $v = substr($value, strripos($value," ")+1);
                if(strtolower($v)=='asc'){
                    $this->options['sort'][$k] = 1;
                }else{
                    $this->options['sort'][$k] = -1;
                }
            }
        }
        return $this;
    }


    /**
     *
     * @param $fields
     * @return $this
     */
    public function field($fields){
        $fields = trim($fields);
        $fieldArray = explode(',', $fields);
        foreach($fieldArray as $field){
            $this->options['projection'][$field] = 1;
        }
        return $this;
    }


    public function limit($limit, $skip=0){
        $this->options['limit'] = intval($limit);
        if(!empty($skip)) {
            $this->options['skip'] = intval($skip);
        }
    }

    public function find(){
        $this->limit(1);
        $data = yield $this->get();
        return !empty($data[0])?$data[0]:(object)[];
    }

    public function get(){
        $query = [
            'method' => 'get',
            'param' => [$this->collection, $this->filter, $this->options],
        ];
        return $this->query($query);
    }

    /**
     * 添加
     * @param $data
     * @return mixed
     */
    public function add($data){
        $query = [
            'method' => 'insert',
            'param' => [$this->collection, $data],
        ];
        return $this->query($query);
    }


    /**
     * 更新
     * @param $data
     * @param bool $upsert
     */
    public function save($data, $upsert=false){
        $updata = ['$set' => $data];
        $query = [
            'method' => 'update',
            'param' => [$this->collection, $this->filter, $updata, $upsert],
        ];
        return $this->query($query);
    }


    public function setInc($field, $num=1){
        $updata = ['$inc' => [ $field => $num ]];
        $query = [
            'method' => 'update',
            'param' => [$this->collection, $this->filter, $updata],
        ];
        return $this->query($query);
    }

    /**
     *删除
     * @param int $limit 0:表示删除全部,1:只删除一条
     */
    public function delete($limit=0){
        $query = [
            'method' => 'delete',
            'param' => [$this->collection, $this->filter, $limit],
        ];
        return $this->query($query);
    }


    /**
     * aggregate
     * @param $pipeline
     * @return mixed
     */
    public function aggregate($pipeline){
        $query = [
            'method' => 'aggregate',
            'param' => [$this->collection, $pipeline],
        ];
        return $this->query($query);
    }


    /**
     * mongodb group 聚合操作
     * @param $key
     * @param $initial
     * @param $reduce
     * @return mixed
     */
    public function group($key, $initial, $reduce){
        $query = [
            'method' => 'group',
            'param' => [$this->collection, $key, $initial, $reduce, $this->filter],
        ];
        return $this->query($query);
    }


    /**
     * count操作
     * @param string $field
     * @return mixed
     */
    public function count($field='*'){
        if($field!=='*'){
            $this->filter[$field] = ['$exists'=>1];
        }
        $query = [
            'method' => 'count',
            'param' => [$this->collection, $this->filter],
        ];
        return $this->query($query);
    }


    /**
     * 实际执行最后操作
     * @param $query
     * @return $this
     */
    protected function query($query){
        $mongoCoroutine = new MongoCoroutine($this->mongoAsynPool);
        $data = $mongoCoroutine->command($query);
        $this->reset();
        return $data;
    }


    protected function reset(){
        $this->filter = [];
        $this->options = [];
    }
}