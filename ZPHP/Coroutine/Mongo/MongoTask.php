<?php
/**
 * Created by PhpStorm.
 * User: zhaoye
 * Date: 2016/12/26
 * Time: 上午10:06
 */

namespace ZPHP\Coroutine\Mongo;

use ZPHP\Core\Log;

class MongoTask{

    public $taskId;
    protected $manager;
    protected $config;
    function __construct($taskId, $config)
    {
        $this->taskId = $taskId;
        $this->config = $config;

    }

    protected function checkManager(){
        if(empty($this->manager)) {
            $this->manager = new \MongoDB\Driver\Manager("mongodb://" . $this->config['host']
                . ":" . $this->config['port']);
        }
    }

    /**
     * 查询
     * @param $collection
     * @param $filter
     * @param array $options
     * @return array
     */
    public function get($collection, $filter,$options=[]){
        $this->checkManager();
        if(!empty($filter['_id'])){
            $filter['_id'] = new \MongoDB\BSON\ObjectId($filter['_id']);
        }
        // 查询数据
        $query = new \MongoDB\Driver\Query($filter, $options);
        $cursor = $this->manager->executeQuery($this->config['database'].'.'.$collection, $query);
        $result = [];
        foreach ($cursor as $document) {
            $document = (array)$document;
            $document['_id'] = (string)$document['_id'];
            $result[] = $document;
        }
        return $result;
    }

    /**
     * 插入操作
     * @param $collection
     * @param $data
     * @return string
     */
    public function insert($collection, $data){
        $this->checkManager();
        $bulk = new \MongoDB\Driver\BulkWrite;
        $_id = $bulk->insert($data);
        $writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000);
        $result = $this->manager->executeBulkWrite($this->config['database'].'.'.$collection, $bulk, $writeConcern);
        if($result){
            $result = (string)$_id;
        }
        return $result;
    }


    /**
     * 更新或者新增
     * @param $collection
     * @param $filter
     * @param $update
     * @param bool $upsert
     * @return mixed
     */
    public function update($collection, $filter, $update, $upsert=false){
        $this->checkManager();
        $bulk = new \MongoDB\Driver\BulkWrite;
        $bulk->update(
            $filter,
            $update,
            ['multi' => true, 'upsert' => $upsert]
        );

        $writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000);
        $result = $this->manager->executeBulkWrite($this->config['database'].'.'.$collection, $bulk, $writeConcern);
        if($result){
            if($upsert){
                $result = $result->getUpsertedCount();
            }else {
                $result = $result->getModifiedCount();
            }
        }
        return $result;
    }


    /**
     * 删除
     * @param $collection
     * @param $filter
     * @param int $limit
     * @return mixed
     */
    public function delete($collection, $filter, $limit=0){
        $this->checkManager();
        $bulk = new \MongoDB\Driver\BulkWrite;
        $bulk->delete($filter, ['limit' => $limit]);   // limit 为 1 时，删除第一条匹配数据

        $writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000);
        $result = $this->manager->executeBulkWrite($this->config['database'].'.'.$collection, $bulk, $writeConcern);
        if($result){
            $result = $result->getDeletedCount();
        }
        return $result;
    }


    /**
     * command类操作
     * @param $command
     * @return cursor
     */
    protected function executeCommand($command){
        $this->checkManager();
        return $this->manager->executeCommand($this->config['database'], new \MongoDB\Driver\Command($command));
    }

    /**
     * aggregate
     * @param $collection
     * @param $pipeline
     * @return array
     */
    public function aggregate($collection, $pipeline){
        $command = [
            'aggregate' => $collection,
            'pipeline' => $pipeline,
            'cursor' => new \stdClass,
        ];
        $result = [];
        $cursor = $this->executeCommand($command);
        if($cursor){
            foreach($cursor as $document){
                $result[] = (array)$document;
            }
        }
        return $result;
    }


    /**
     * mongodb group 自定义函数操作
     * @param $collection
     * @param $key
     * @param $initial
     * @param $reduce
     * @param $filter
     * @return array
     */
    public function group($collection, $key, $initial, $reduce, $filter){
        $command = [
            'group'=>[
                'ns' => $collection,
                'key' => $key,
                'initial' => $initial,
                '$reduce' => new \MongoDB\BSON\Javascript($reduce),
                'query' => $filter,
            ]
        ];
        $cursor = $this->executeCommand($command);
        $data = [];
        if($cursor) {
            $resObjArray = $cursor->toArray()[0]->retval;
            foreach($resObjArray as $obj){
                $data[] = (array)$obj;
            }
        }
        return $data;
    }


    /**
     * 返回count
     * @param $collection
     * @param $filter
     * @return int
     */
    public function count($collection, $filter){
        $command = [
            'count' => $collection,
            'query' => $filter,
        ];
        $cursor = $this->executeCommand($command);
        $num = 0;
        if($cursor) {
            $num = $cursor->toArray()[0]->n;
        }
        return $num;
    }
}