<?php
namespace Lsf\Plugin;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\ConnectionPoolTrait;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;

/**
 * Redis连接池操作类
 * @author mr
 * $Id: redis_pool.php $
 */

class RedisPool
{
    use ConnectionPoolTrait;

    private $_pool  = [];
    private $_redis = [];
    private $_nodeName = 'redis';

    /**
     * 创建连接池
     * @param string  $nodeName
     * @param  array  $config
     * @return void
     */
    public function createRedisPool($nodeName='redis', $config){
        // 检查配置
        if(!isset($config['host']) || empty($config['host'])){
            throw new \Exception('Redis node ' . $nodeName . ' config host empty');
        }
        if(!isset($config['port']) || empty($config['port'])){
            throw new \Exception('Redis node ' . $nodeName . ' config port empty');
        }
        if(!isset($config['pool.min_active']) || empty($config['pool.min_active'])){
            throw new \Exception('Redis node ' . $nodeName . ' config pool.min_active empty');
        }
        if(!isset($config['pool.max_active']) || empty($config['pool.max_active'])){
            throw new \Exception('Redis node ' . $nodeName . ' config pool.max_active empty');
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
        $redisConfig = [
            'host' => $config['host'],
            'port' => $config['port'],
        ];
        if(isset($config['database']) && !empty($config['database'])){
            $redisConfig['database'] = $config['database'];
        }
        if(isset($config['password']) && !empty($config['password'])){
            $redisConfig['password'] = $config['password'];
        }
        $pool = new ConnectionPool($poolConfig, new PhpRedisConnector, $redisConfig);
        $pool->init();
        $this->addConnectionPool($nodeName, $pool);
    }

    /**
     * 获取redis连接
     * @param  string $nodeName
     * @return object
     */
    public function redis($nodeName='redis'){
        $this->_nodeName = $nodeName;
        $this->_pool[$nodeName] = $this->getConnectionPool($nodeName);
        try{
            // 协程下只borrow一次
            $cid = \Lsf\Core::$php->getCid();
            if(!isset($this->_redis[$nodeName][$cid]) || !is_object($this->_redis[$nodeName][$cid])){
                $this->_redis[$nodeName][$cid] = $this->_pool[$nodeName]->borrow();
            }
        }catch(\Smf\ConnectionPool\BorrowConnectionTimeoutException $e){
            $logData = [
                'node_name' => $nodeName,
                'code'      => $e->getCode(),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine()
            ];
            \Lsf\Loader::plugin('Log')->error(9000017, $logData, 'redis_connection_pool');
        }
        return $this;
    }

    /**
     * 关闭连接池
     * @param  void
     * @return void
     */
    public function closeRedisPool(){
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
        if(isset($serverConfig['module']['redis']) && $serverConfig['module']['redis'] === TRUE
            &&isset($serverConfig['redis']['node_name']) && !empty($serverConfig['redis']['node_name'])){

            $nodeNameArr = $serverConfig['redis']['node_name'];
            foreach($nodeNameArr as $nodeName){
                if(isset($this->_redis[$nodeName][$cid])){
                    $this->_pool[$nodeName]->return($this->_redis[$nodeName][$cid]);
                    unset($this->_redis[$nodeName][$cid]);
                }
            }

//            if(isset($this->_redis[$cid])){
//                $this->_pool->return($this->_redis[$cid]);
//                unset($this->_redis[$cid]);
//            }
        }
    }

    /**
     * __call魔术方法
     * @param  string  $method
     * @param  array   $args
     * @return mixed   $result
     */
    public function __call($method, $args){
        $upStr = strtoupper($method);
        // 检查codis限制方法
        $codisBlackList = ['KEYS', 'MOVE', 'OBJECT', 'RENAME', 'RENAMENX', 'SORT', 'SCAN', 'BITOP', 'MSETNX', 'BLPOP', 'BRPOP', 'BRPOPLPUSH', 'PSUBSCRIBE', 'PUBLISH', 'PUNSUBSCRIBE', 'SUBSCRIBE', 'UNSUBSCRIBE', 'DISCARD', 'EXEC', 'MULTI', 'UNWATCH', 'WATCH', 'SCRIPT EXISTS', 'SCRIPT FLUSH', 'SCRIPT KILL', 'SCRIPT LOAD', 'AUTH', 'ECHO', 'SELECT', 'BGREWRITEAOF', 'BGSAVE', 'CLIENT KILL', 'CLIENT LIST', 'CONFIG GET', 'CONFIG SET', 'CONFIG RESETSTAT', 'DBSIZE', 'DEBUG OBJECT', 'DEBUG SEGFAULT', 'FLUSHALL', 'FLUSHDB', 'INFO', 'LASTSAVE', 'MONITOR', 'SAVE', 'SHUTDOWN', 'SLAVEOF', 'SLOWLOG', 'SYNC', 'TIME'];
        if(in_array($upStr, $codisBlackList) || $this->_redis[$this->_nodeName] === FALSE){
            return FALSE;
        }
        $result = FALSE;
        $excetpion = 0;
        $cid = \Lsf\Core::$php->getCid();
        // 保证由于borrow无法获取到可用连接的时候不会报错
        if(!isset($this->_redis[$this->_nodeName][$cid]) || !is_object($this->_redis[$this->_nodeName][$cid])){
            return FALSE;
        }
        try{
            $result = call_user_func_array([$this->_redis[$this->_nodeName][$cid], $method], $args);
        }catch(\RedisException $e){
            $excetpion = 1;
            $logData = [
                'node'      => $this->_nodeName,
                'code'      => $e->getCode(),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine()
            ];
            \Lsf\Loader::plugin('Log')->error(9000018, $logData, 'redis_connection_pool');
            // reconnect
            $this->_pool[$this->_nodeName]->removeConnection($this->_redis[$this->_nodeName][$cid]);
            $this->_redis[$this->_nodeName][$cid] = $this->_pool[$this->_nodeName]->createConnection();
            // retry
            try{
                $result = call_user_func_array([$this->_redis[$this->_nodeName][$cid], $method], $args);
                $excetpion = 0;
                $logData['description'] = 'redis connection reconnected and retry success';
                \Lsf\Loader::plugin('Log')->info('', $logData, 'redis_connection_pool');
            }catch(\RedisException $e){
                $logData = [
                    'node'      => $this->_nodeName,
                    'code'      => $e->getCode(),
                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine()
                ];
                \Lsf\Loader::plugin('Log')->error(9000020, $logData, 'redis_connection_pool');
            }
        }
        // 从每一次redis操作都返回资源修改为每一次协程执行完成后再返回资源
        // $this->_pool->return($this->_redis[$cid]);
        // unset($this->_redis[$cid]);
        if($excetpion == 1){
            return FALSE;
        }else{
            return $result;
        }
    }

}