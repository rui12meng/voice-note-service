<?php
namespace Lsf\Plugin;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\ConnectionPoolTrait;
use Smf\ConnectionPool\Connectors\CoroutineMysqlConnector;
use Swoole\Coroutine\MySQL;

/**
 * MySQL连接池操作类
 * @author mr
 * $Id: mysql_pool.php $
 */

class MysqlPool
{
    use ConnectionPoolTrait;

    private $_pool      = [];
    private $_mysql     = [];
    private $_runtime   = [];

    /**
     * 创建连接池
     * @param  string  $nodeName
     * @param  array   $config
     * @return void
     */
    public function createMysqlPool($nodeName, $config){
        // 检查配置
        if(!isset($config['host']) || empty($config['host'])){
            throw new \Exception('Mysql node ' . $nodeName . ' config host empty');
        }
        if(!isset($config['port']) || empty($config['port'])){
            throw new \Exception('Mysql node ' . $nodeName . ' config port empty');
        }
        if(!isset($config['user']) || empty($config['user'])){
            throw new \Exception('Mysql node ' . $nodeName . ' config user empty');
        }
        if(!isset($config['password']) || empty($config['password'])){
            throw new \Exception('Mysql node ' . $nodeName . ' config password empty');
        }
        if(!isset($config['database']) || empty($config['database'])){
            throw new \Exception('Mysql node ' . $nodeName . ' config database empty');
        }
        if(!isset($config['pool.min_active']) || empty($config['pool.min_active'])){
            throw new \Exception('Mysql node ' . $nodeName . ' config pool.min_active empty');
        }
        if(!isset($config['pool.max_active']) || empty($config['pool.max_active'])){
            throw new \Exception('Mysql node ' . $nodeName . ' config pool.max_active empty');
        }
        $poolConfig = [
            'minActive' => $config['pool.min_active'],
            'maxActive' => $config['pool.max_active'],
        ];
        // 最大等待时间
        if(isset($config['pool.borrow_wait_time']) && !empty($config['pool.borrow_wait_time'])){
            $poolConfig['maxWaitTime'] = $config['pool.borrow_wait_time'];
        }else{
            $poolConfig['maxWaitTime'] = 2;
        }
        $mysqlConfig = [
            'host'        => $config['host'],
            'port'        => $config['port'],
            'user'        => $config['user'],
            'password'    => $config['password'],
            'database'    => $config['database'],
        ];
        // 默认3s超时
        if(isset($config['pool.timeout']) && !empty($config['pool.timeout'])){
            $mysqlConfig['timeout'] = $config['pool.timeout'];
        }else{
            $mysqlConfig['timeout'] = 3;
        }
        // 默认字符集
        if(isset($config['charset']) && !empty($config['charset'])){
            $mysqlConfig['charset'] = $config['charset'];
        }else{
            $mysqlConfig['charset'] = 'utf8mb4';
        }
        if(isset($config['pool.strict_type']) && $config['pool.strict_type'] == 1){
            $mysqlConfig['strict_type'] = TRUE;
        }else{
            $mysqlConfig['strict_type'] = FALSE;
        }
        if(isset($config['pool.fetch_mode']) && $config['pool.fetch_mode'] == 1){
            $mysqlConfig['fetch_mode'] = TRUE;
        }else{
            $mysqlConfig['fetch_mode'] = FALSE;
        }
        $pool = new ConnectionPool($poolConfig, new CoroutineMysqlConnector, $mysqlConfig);
        $pool->init();
        $this->addConnectionPool($nodeName, $pool);
    }

    /**
     * 获取mysql连接
     * @param  string  $nodeName
     * @return object
     */
    public function db($nodeName){
        $this->_pool[$nodeName] = $this->getConnectionPool($nodeName);
        try{
            // 协程下只borrow一次
            $cid = \Lsf\Core::$php->getCid();
            if(!isset($this->_mysql[$nodeName][$cid]) || !is_object($this->_mysql[$nodeName][$cid])){
                $this->_mysql[$nodeName][$cid] = $this->_pool[$nodeName]->borrow();
            }
        }catch(\Smf\ConnectionPool\BorrowConnectionTimeoutException $e){
            $logData = [
                'node_name' => $nodeName,
                'code'      => $e->getCode(),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine()
            ];
            \Lsf\Loader::plugin('Log')->error(9000016, $logData, 'mysql_connection_pool');
        }
        return $this;
    }

    /**
     * 关闭连接池
     * @param  void
     * @return void
     */
    public function closeMysqlPool(){
        $this->closeConnectionPools();
    }

    /**
     * 回收资源
     * @param  void
     * @return void
     */
    public function recycle(){
        $cid = \Lsf\Core::$php->getCid();
        // 获取所有节点配置
        $serverConfig = \Lsf\Loader::config('server', TRUE);
        if(isset($serverConfig['module']['mysql']) &&
            $serverConfig['module']['mysql'] === TRUE &&
            isset($serverConfig['mysql']['node_name']) &&
            !empty($serverConfig['mysql']['node_name'])
        ){
            $nodeNameArr = $serverConfig['mysql']['node_name'];
            foreach($nodeNameArr as $nodeName){
                if(isset($this->_mysql[$nodeName][$cid])){
                    $this->_pool[$nodeName]->return($this->_mysql[$nodeName][$cid]);
                    unset($this->_mysql[$nodeName][$cid]);
                }
                if(isset($this->_runtime[$nodeName][$cid])){
                    unset($this->_runtime[$nodeName][$cid]);
                }
            }
        }
    }

    /**
     * __call魔术方法
     * @param  string  $method
     * @param  array   $args
     * @return mixed   $result
     */
    public function __call($method, $args){
        // 获取节点名称
        if($method == 'query'){
            if(!isset($args[1]) || empty($args[1])){
                \Lsf\Loader::plugin('Log')->error(9000021, ['method' => $method, 'args' => $args], 'mysql_connection_pool');
                return FALSE;
            }else{
                $nodeName = $args[1];
                unset($args[1]);
            }
        }else{
            if(!isset($args[0]) || empty($args[0])){
                \Lsf\Loader::plugin('Log')->error(9000021, ['method' => $method, 'args' => $args], 'mysql_connection_pool');
                return FALSE;
            }else{
                $nodeName = $args[0];
                unset($args[0]);
            }
        }
        return $this->_runSql($nodeName, $method, $args);
    }

    /**
     * 执行SQL
     * @param  string  $nodeName
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     */
    private function _runSql($nodeName, $method, $args){
        $startTime = microtime(TRUE);
        $cid = \Lsf\Core::$php->getCid();
        // 保证由于borrow无法获取到可用连接的时候不会报错
        if(!isset($this->_mysql[$nodeName][$cid]) || !is_object($this->_mysql[$nodeName][$cid])){
            return FALSE;
        }
        $result = call_user_func_array(array($this->_mysql[$nodeName][$cid], $method), $args);
        $this->_runtime[$nodeName][$cid] = \Lsf\Performance::countRunTime($startTime);
        // 连接池retry机制
        if($this->_mysql[$nodeName][$cid]->errno > 0 && in_array($this->_mysql[$nodeName][$cid]->errno, [2006, 2013])){
            // 记录本次执行错误
            $logData = [
                'node_name' => $nodeName,
                'method'    => $method,
                'args'      => $args
            ];
            $logData['errno'] = $this->_mysql[$nodeName][$cid]->errno;
            $logData['error'] = $this->_mysql[$nodeName][$cid]->error;
            \Lsf\Loader::plugin('Log')->warning(9000014, $logData, 'mysql_connection_pool');
            // 数据库连接池重连
            $this->_pool[$nodeName]->removeConnection($this->_mysql[$nodeName][$cid]);
            $this->_mysql[$nodeName][$cid] = $this->_pool[$nodeName]->createConnection();
            // 再次尝试执行SQL
            $result = $this->_runSql($nodeName, $method, $args);
            if($this->_mysql[$nodeName][$cid]->errno > 0){
                $logData['errno'] = $this->_mysql[$nodeName][$cid]->errno;
                $logData['error'] = $this->_mysql[$nodeName][$cid]->error;
                \Lsf\Loader::plugin('Log')->error(9000015, $logData, 'mysql_connection_pool');
            }else{
                $logData['description'] = 'mysql connection reconnected and retry success';
                \Lsf\Loader::plugin('Log')->info('', $logData, 'mysql_connection_pool');
            }
        }
        // 从每一次数据库操作都返回资源修改为每一次协程执行完成后再返回资源
        // $this->_pool[$nodeName]->return($this->_mysql[$nodeName][$cid]);
        return $result;
    }

    /**
     * 获取错误码
     * @param  string  $nodeName
     * @return int
     */
    public function errno($nodeName){
        $cid = \Lsf\Core::$php->getCid();
        return $this->_mysql[$nodeName][$cid]->errno;
    }

    /**
     * 获取错误信息
     * @param  string  $nodeName
     * @return string
     */
    public function error($nodeName){
        $cid = \Lsf\Core::$php->getCid();
        return $this->_mysql[$nodeName][$cid]->error;
    }

    /**
     * 获取SQL执行时间
     * @param  string  $nodeName
     * @return string
     */
    public function runtime($nodeName){
        $cid = \Lsf\Core::$php->getCid();
        return $this->_runtime[$nodeName][$cid];
    }

    /**
     * 获取插入主键id
     * @param  void
     * @return int
     */
    public function insertId($nodeName){
        $cid = \Lsf\Core::$php->getCid();
        return $this->_mysql[$nodeName][$cid]->insert_id;
    }

    /**
     * 获取影响行数
     * @param  void
     * @return int
     */
    public function affectedRows($nodeName){
        $cid = \Lsf\Core::$php->getCid();
        return $this->_mysql[$nodeName][$cid]->affected_rows;
    }

}