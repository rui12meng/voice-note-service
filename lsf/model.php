<?php
namespace Lsf;

/**
 * Model操作类
 * @author
 * $Id: model.php $
 */

class Model
{
    public $nodeName    = 'mysql';
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = '';
    private $_db;
    private $_mysqlPool;

    /**
     * 构造函数
     * @param  object  $swoole
     * @param  string  $node
     * @return void
     */
    public function __construct(){
        $this->build = new \Database\Build();
        $this->table = $this->tablePrefix . $this->table;
        $this->_mysqlPool = \Lsf\Loader::plugin('MysqlPool');
    }

    /**
     * __call
     * @param  string  $method
     * @param  array   $args
     * @return mixed   $result
     */
    public function __call($method, $args){
        if(method_exists($this, $method) === FALSE){
            throw new \Exception('Model '. $method . ' method not exists');
        }
        // 动态从连接池中获取新的db对象
        $this->_db = $this->_mysqlPool->db($this->nodeName);
        $result = call_user_func_array(array($this, $method), $args);
        $this->_db->recycle($this->nodeName);
        return $result;
    }

    /**
     * find
     * [说明]
     * 通过主键获取一条记录
     * @param  string  $columns
     * @param  int     $id
     * @return mixed
     */
    private function find($columns, $id){
        $where = $this->build->parseWhere([$this->primary => $id]);
        $sql = 'SELECT ';
        $sql.= $columns;
        $sql.= ' FROM ' . $this->table;
        $sql.= ' WHERE ' . $where;
        $sql.= ' LIMIT 1';
        $result = $this->_query($sql, __FUNCTION__);
        if($result !== FALSE){
            return (is_array($result) && !empty($result)) ? $result[0] : [];
        }else{
            return FALSE;
        }
    }

    /**
     * select
     * [说明]
     * 根据不同条件获取一条或多条记录
     * @param  string  $columns
     * @param  array   $where
     * @param  string  $order
     * @param  string  $limit
     * @param  bool    $forUpdate
     * @return mixed
     */
    private function select($columns, $where = [], $order = '', $limit = 10, $forUpdate = FALSE){
        $where = $this->build->parseWhere($where);
        $sql = 'SELECT ';
        $sql.= $columns;
        $sql.= ' FROM ' . $this->table;
        $sql.= ' WHERE ' . $where;
        if($order){
            $sql.= ' ORDER BY ' . $order;
        }
        $sql.= ' LIMIT ' . $limit;
        if($forUpdate){
            $sql.= ' FOR UPDATE';
        }
        return $this->_query($sql, __FUNCTION__);
    }

    /**
     * count
     * @param  string  $columns
     * @param  array   $where
     * @return mixed
     */
    private function count($columns, $where = [], $lock = 0){
        $where = $this->build->parseWhere($where);
        $countSql = 'count(' . $columns . ')';
        $sql = 'SELECT ';
        $sql.= $countSql;
        $sql.= ' FROM ' . $this->table;
        $sql.= ' WHERE ' . $where;
        if($lock){
            $sql.= ' FOR UPDATE';
        }
        $result = $this->_query($sql, __FUNCTION__);
        if($result !== FALSE){
            return $result[0][$countSql];
        }else{
            return FALSE;
        }
    }

    /**
     * insert
     * [说明]
     * 写入成功返回主键id
     * @param  array  $data
     * @return mixed
     */
    private function insert($data){
        $result = $this->build->parseInsertData($data);
        list($columns, $values) = $result;
        $sql = 'INSERT INTO ';
        $sql.= $this->table;
        $sql.= ' ' . $columns . ' VALUES ' . $values;
        $result = $this->_query($sql, __FUNCTION__);
        if($result !== FALSE){
            return $this->_db->insertId($this->nodeName);
        }else{
            return FALSE;
        }
    }

    /**
     * 批量insert
     * [说明]
     * 写入成功返回影响行数
     * @param  array  $data
     * @return mixed
     */
    private function batchInsert($data){
        $batchValues = [];
        foreach($data as $oneData){
            $parseData = $this->build->parseInsertData($oneData);
            list($columns, $values) = $parseData;
            $batchValues[] = $values;
        }
        $sql = 'INSERT INTO ';
        $sql.= $this->table;
        $sql.= ' ' . $columns . ' VALUES ' . implode(',', $batchValues);
        $result = $this->_query($sql, __FUNCTION__);
        if($result !== FALSE){
            return $this->_db->affectedRows($this->nodeName);
        }else{
            return FALSE;
        }
    }

    /**
     * update
     * [说明]
     * 更新成功返回影响行数
     * @param  array  $data
     * @param  array  $where
     * @param  int    $limit
     * @return bool
     */
    private function update($data, $where, $limit = 1){
        $sets = $this->build->parseUpdateData($data);
        $where = $this->build->parseWhere($where);
        $sql = 'UPDATE ';
        $sql.= $this->table;
        $sql.= ' SET' . $sets;
        $sql.= ' WHERE ' . $where;
        $sql.= ' LIMIT ' . $limit;
        $result = $this->_query($sql, __FUNCTION__);
        if($result !== FALSE){
            return $this->_db->affectedRows($this->nodeName);
        }else{
            return FALSE;
        }
    }

    /**
     * query
     * [说明]
     * 自定义查询，非特殊情况不建议使用
     * @param  string  $sql
     * @return mixed
     */
    private function query($sql){
        return $this->_query($sql, __FUNCTION__);
    }

    /**
     * 启动事务
     * @param  void
     * @return bool
     */
    private function begin(){
        try{
            $result = $this->_db->begin($this->nodeName);
            if($result === FALSE){
                $errData = [
                    'method'    => __FUNCTION__,
                    'node_name' => $this->nodeName,
                    'errno'     => $this->_db->errno($this->nodeName),
                    'error'     => $this->_db->error($this->nodeName),
                ];
                \Lsf\Loader::plugin('Log')->error(9000023, $errData, 'mysql_error');
            }else{
                \Lsf\Loader::plugin('Log')->debug('', ['method' => __FUNCTION__, 'node_name' => $this->nodeName], 'mysql_transaction');
            }
            return $result;
        }catch(\Swoole\MySQL\Exception $e){
            $errData = [
                'method'    => __FUNCTION__,
                'node_name' => $this->nodeName,
                'ecode'     => $e->getCode(),
                'emsg'      => $e->getMessage()
            ];
            \Lsf\Loader::plugin('Log')->error(9000023, $errData, 'mysql_transaction');
            return FALSE;
        }
    }

    /**
     * 提交事务
     * @param  void
     * @return bool
     */
    private function commit(){
        $result = $this->_db->commit($this->nodeName);
        if($result === FALSE){
            $errData = [
                'method'    => __FUNCTION__,
                'node_name' => $this->nodeName,
                'errno'     => $this->_db->errno($this->nodeName),
                'error'     => $this->_db->error($this->nodeName),
            ];
            \Lsf\Loader::plugin('Log')->error(9000023, $errData, 'mysql_error');
        }else{
            \Lsf\Loader::plugin('Log')->debug('', ['method' => __FUNCTION__, 'node_name' => $this->nodeName], 'mysql_transaction');
        }
        return $result;
    }

    /**
     * 回滚事务
     * @param  void
     * @return bool
     */
    private function rollback(){
        $result = $this->_db->rollback($this->nodeName);
        if($result === FALSE){
            $errData = [
                'method'    => __FUNCTION__,
                'node_name' => $this->nodeName,
                'errno'     => $this->_db->errno($this->nodeName),
                'error'     => $this->_db->error($this->nodeName),
            ];
            \Lsf\Loader::plugin('Log')->error(9000023, $errData, 'mysql_error');
        }else{
            \Lsf\Loader::plugin('Log')->debug('', ['method' => __FUNCTION__, 'node_name' => $this->nodeName], 'mysql_transaction');
        }
        return $result;
    }

    /**
     * 执行SQL
     * @param  string  $sql
     * @param  string  $method
     * @return mixed
     */
    private function _query($sql, $method){
        $result = $this->_db->query($sql, $this->nodeName);
        if($result === FALSE){
            $errData = [
                'method'    => $method,
                'errno'     => $this->_db->errno($this->nodeName),
                'error'     => $this->_db->error($this->nodeName),
                'node_name' => $this->nodeName,
                'sql'       => $sql,
                'runtime'   => $this->_db->runtime($this->nodeName)
            ];
            \Lsf\Loader::plugin('Log')->error(9000023, $errData, 'mysql_error');
        }else{
            $succData = [
                'method'        => $method,
                'node_name'     => $this->nodeName,
                'sql'           => $sql,
                'runtime'       => $this->_db->runtime($this->nodeName),
                'affected_rows' => $this->_db->affectedRows($this->nodeName)
            ];
            \Lsf\Loader::plugin('Log')->debug('', $succData, 'mysql_query');
        }
        return $result;
    }

}