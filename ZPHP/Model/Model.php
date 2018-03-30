<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 16/7/16
 * Time: 下午10:17
 */


namespace ZPHP\Model;



use ZPHP\Core\Db;
use ZPHP\Coroutine\Mysql\MysqlAsynPool;
use ZPHP\Coroutine\Mysql\MySqlCoroutine;

class Model {


    /**
     * @var MysqlAsynPool
     */
    public $mysqlPool;


    protected $table='';
    protected $select='*';
    protected $where='';
    public $lastSql='';

    public $join='';
    public $use_index='';
    public $union='';
    public $group='';
    public $having='';
    public $order='';
    public $limit='';
    public $for_update='';
    protected $_transId;

    protected $_fields;
    // 数据库表达式
    protected $exp = array('eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE','in'=>'IN','notin'=>'NOT IN','not in'=>'NOT IN','between'=>'BETWEEN','not between'=>'NOT BETWEEN','notbetween'=>'NOT BETWEEN');


    public function __construct($tableName, $mysqlPool){
        $this->table = $tableName;
        $this->mysqlPool = $mysqlPool;

    }


    /**
     * set field which need select
     * @param string $fields
     * @return $this
     */
    public function field($fields='*'){
        $this->select = $fields;
        return $this;
    }

    /**
     * 获取数量
     * @param string $field
     * @return object
     */
    public function count($field='*'){
        $this->select = "count(".$field.") as num";
        $this->limit(1);
        $data = yield  $this->get();
        return !empty($data[0]['num'])?$data[0]['num']:0;
    }

    /*
     * 根据where解析查询条件
     * @zhaoye
     */
    public function where($where){
        $whereStr = '';
        if(is_string($where)) {
            // 直接使用字符串条件
            $whereStr = $where;
        }else{ // 使用数组表达式
            // 默认进行 AND 运算
            $operate    =   ' AND ';
            foreach ($where as $key=>$val){
                if($key=='_string') {
                    // 解析特殊条件表达式
                    $whereStr   .=  '( '.$val.' )';
                }else{
                    $whereStr .= $this->parseWhereItem($key,$val);
                }
                $whereStr .= $operate;
            }
            $whereStr = substr($whereStr,0,-strlen($operate));
        }
        $this->where =  empty($whereStr)?'':' where '.$whereStr;
        return $this;
    }

    /**
     * value分析
     * @access protected
     * @param mixed $value
     * @return string
     */
    protected function parseValue($value) {
        if(is_string($value)) {
            $value =  strpos($value,':') === 0? addslashes($value) : '\''.addslashes($value).'\'';
        }elseif(isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp'){
            $value =  addslashes($value[1]);
        }elseif(is_array($value)) {
            $value =  array_map(array($this, 'parseValue'),$value);
        }elseif(is_bool($value)){
            $value =  $value ? '1' : '0';
        }elseif(is_null($value)){
            $value =  'null';
        }
        return $value;
    }


    protected function _parseType(&$data, $key){
        $value = $data[$key];
        if(is_array($value) ){
            if(!empty($value[0]) && $value[0]=='exp'){
                $value = addslashes($value[1]);
            }
        }else {
            if (!empty($this->_fields[$key])) {
                $fieldType = strtolower($this->_fields[$key]);
                if (false !== strpos($fieldType, 'enum')) {
                    // 支持ENUM类型优先检测
                } elseif (false === strpos($fieldType, 'bigint') && false !== strpos($fieldType, 'int')) {
                    $value = intval($value);
                } elseif (false !== strpos($fieldType, 'float') || false !== strpos($fieldType, 'double')) {
                    $value = floatval($value);
                } elseif (false !== strpos($fieldType, 'varchar')
                    || false !== strpos($fieldType, 'char')
                    || false !== strpos($fieldType, 'text')
                    || false !== strpos($fieldType, 'datetime')
                ) {
                    $value = '\'' . addslashes($value) . '\'';
                } elseif (false !== strpos($fieldType, 'bool')) {
                    $value = (bool)$value;
                }
            }
        }
        return $value;
    }

    // where子单元分析
    protected function parseWhereItem($key,$val) {
        $whereStr = '';
        $key = '`'.$key.'`';
        if(is_array($val)) {
            if(is_string($val[0])) {
                $exp	=	strtolower($val[0]);
                if(preg_match('/^(eq|neq|gt|egt|lt|elt)$/',$exp)) { // 比较运算
                    $whereStr .= $key.' '.$this->exp[$exp].' '.$this->parseValue($val[1]);
                }elseif(preg_match('/^(notlike|like)$/',$exp)){// 模糊查找
                    if(is_array($val[1])) {
                        $likeLogic  =   isset($val[2])?strtoupper($val[2]):'OR';
                        if(in_array($likeLogic,array('AND','OR','XOR'))){
                            $like       =   array();
                            foreach ($val[1] as $item){
                                $like[] = $key.' '.$this->exp[$exp].' '.$this->parseValue($item);
                            }
                            $whereStr .= '('.implode(' '.$likeLogic.' ',$like).')';
                        }
                    }else{
                        $whereStr .= $key.' '.$this->exp[$exp].' '.$this->parseValue($val[1]);
                    }
                }elseif('bind' == $exp ){ // 使用表达式
                    $whereStr .= $key.' = :'.$val[1];
                }elseif('exp' == $exp ){ // 使用表达式
                    $whereStr .= $key.' '.$val[1];
                }elseif(preg_match('/^(notin|not in|in)$/',$exp)){ // IN 运算
                    if(isset($val[2]) && 'exp'==$val[2]) {
                        $whereStr .= $key.' '.$this->exp[$exp].' '.$val[1];
                    }else{
                        if(is_string($val[1])) {
                            $val[1] =  explode(',',$val[1]);
                        }
                        $zone      =   implode(',',$this->parseValue($val[1]));
                        $whereStr .= $key.' '.$this->exp[$exp].' ('.$zone.')';
                    }
                }elseif(preg_match('/^(notbetween|not between|between)$/',$exp)){ // BETWEEN运算
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $whereStr .=  $key.' '.$this->exp[$exp].' '.$this->parseValue($data[0]).' AND '.$this->parseValue($data[1]);
                }else{
                    throw new \Exception("表达式错误");
                }
            }else {
                $count = count($val);
                $rule  = isset($val[$count-1]) ? (is_array($val[$count-1]) ? strtoupper($val[$count-1][0]) : strtoupper($val[$count-1]) ) : '' ;
                if(in_array($rule,array('AND','OR','XOR'))) {
                    $count  = $count -1;
                }else{
                    $rule   = 'AND';
                }
                for($i=0;$i<$count;$i++) {
                    $data = is_array($val[$i])?$val[$i][1]:$val[$i];
                    if('exp'==strtolower($val[$i][0])) {
                        $whereStr .= $key.' '.$data.' '.$rule.' ';
                    }else{
                        $whereStr .= $this->parseWhereItem($key,$val[$i]).' '.$rule.' ';
                    }
                }
                $whereStr = '( '.substr($whereStr,0,-4).' )';
            }
        }else {
            $whereStr .= $key.' = '.$this->parseValue($val);
        }
        return $whereStr;
    }



    public function order($orderStr){
        $this->order = 'order by '.$orderStr;
        return $this;
    }

    protected function doBeforeQuery($method){
        yield $this->_setFields();
    }


    protected function _setFields(){
        if(empty($this->_fields)) {
            $sql = 'SHOW COLUMNS FROM ' . $this->table;
            $data = ['sql' => $sql];
            Db::setSql($sql);
            $mysqlCoroutine = new MySqlCoroutine($this->mysqlPool);
            $fieldsRes = yield $mysqlCoroutine->command($data);
            if(!empty($fieldsRes)){
                foreach ($fieldsRes['result'] as $key => $value){
                    $this->_fields[$value['Field']] = $value['Type'];
                }
            }
        }
    }

    /**
     * 获取筛选条件的所有数据
     * @return array
     */
    public function get(){
        $data =  yield $this->query($this->makesql());
        return $data['result']===false?false:!empty($data['result'])?$data['result']:[];
    }


    /**
     * 获取表的一条数据
     * @return array or null
     */
    public function find(){
        $this->limit(1);
        $data = yield  $this->get();
        return $data===false?false:!empty($data[0])?$data[0]:(object)[];
    }

    /**
     * 插入数据
     * @param $data
     * @param bool $replace
     * @return \Generator
     * @throws \Exception
     */
    public function add($data, $replace=false){
        yield $this->doBeforeQuery(__METHOD__);
        $fields = [];
        $values = [];
        foreach($data as $key => $value){
            $fields[] = $key;
            $values[] = $this->_parseType($data, $key);
        }
        $sql = (true===$replace?'REPLACE':'INSERT').' INTO '.$this->table
            .' ('.'`' . implode('`,`', $fields) . '`'.') VALUES ('.implode(',', $values).')';
        $data = yield $this->query($sql);
        return $data['result']===false?false:$data['insert_id'];
    }


    /**
     * 更新操作
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function save($data){
        yield $this->doBeforeQuery(__METHOD__);
        $updateField = [];
        foreach($data as $key => $value){
            $updateField[] = '`' .$key. '` = ' .$this->_parseType($data, $key);
        }
        $sql =  "UPDATE {$this->table} SET ".implode(',', $updateField)." {$this->where}";
        $res = yield $this->query($sql);
        return $res['result']===false?false:$res['affected_rows'];
    }

    /**
     * 自增
     * @param $field
     * @param string $add
     * @return bool
     * @throws \Exception
     */
    public function setInc($field, $add='1'){
        $sql =  "UPDATE {$this->table} SET `$field` = `$field` + $add {$this->where}";
        $data = yield $this->query($sql);
        return $data['result']===false?false:$data['affected_rows'];
    }

    /**
     * 自增
     * @param $field
     * @param string $add
     * @return bool
     * @throws \Exception
     */
    public function setDec($field, $dec='1'){
        $sql =  "UPDATE {$this->table} SET `$field` = `$field` - $dec {$this->where}";
        $data = yield $this->query($sql);
        return $data['result']===false?false:$data['affected_rows'];
    }

    /**
     * 查询的条数
     * @param $limit
     * @return null
     */
    function limit($limit)
    {
        if (!empty($limit))
        {
            $_limit = explode(',', $limit, 2);
            if (count($_limit) == 2)
            {
                $this->limit = 'limit ' . (int)$_limit[0] . ',' . (int)$_limit[1];
            }
            else
            {
                $this->limit = "limit " . (int)$limit;
            }
        }
        else
        {
            $this->limit = '';
        }
        return $this;
    }

    /**开始事务
     * @return MySqlCoroutine
     */
    public function startTrans(){
        $transId = uniqid();
        $data = yield $this->command(['trans_id'=>$transId, 'sql'=>'begin']);
        return $data['result']===false?false:$data['result'];
    }


    /**
     * 设置事务编号
     * @param $transId
     * @return $this
     */
    public function setTransId($transId){
        $this->_transId = $transId;
        return $this;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function commit(){
        return $this->endTrans('commit');
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function rollback(){
        return $this->endTrans('rollback');
    }

    /**
     * 结束事务
     * @param $sql
     * @return bool
     * @throws \Exception
     */
    protected function endTrans($sql){
        if(empty($this->_transId)){
            throw new \Exception("You must setTransId before execute sql[$sql]!");
        }
        $data = yield $this->query($sql);
        return $data['result']===false?false:$data['result'];
    }

    /**
     * 执行查询部分
     * @param $sql
     * @return $this
     * @throws \Exception
     */
    public function query($sql){
        $_sql = trim($sql);
        if (empty($_sql)) {
            throw new \Exception('sql 不能为空');
        }
        $data = ['sql'=>$_sql];
        if(!empty($this->_transId)){
            $data['trans_id'] = $this->_transId;
        }
        return $this->command($data);
    }

    /**
     * @param $data
     * @return $this
     */
    protected function command($data){
        $this->reset();
        Db::setSql($data['sql']);
        $mysqlCoroutine = new MySqlCoroutine($this->mysqlPool);
        return $mysqlCoroutine->command($data);
    }

    //生成sql
    protected function makesql(){
        $sql = "select {$this->select} from {$this->table}";
        $sql .= implode(' ',
            array(
                $this->join,
                $this->use_index,
                $this->where,
                $this->union,
                $this->group,
                $this->having,
                $this->order,
                $this->limit,
                $this->for_update,
            ));
        return $sql;
    }



    protected function reset(){
        $this->select = '*';
        $this->join = '';
        $this->use_index = '';
        $this->where = '';
        $this->union = '';
        $this->group = '';
        $this->having = '';
        $this->order = '';
        $this->limit = '';
        $this->for_update = '';
        $this->_transId = '';
    }
}