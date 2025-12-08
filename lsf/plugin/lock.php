<?php
namespace Lsf\Plugin;

/**
 * 内存锁插件
 * @author mr
 * $Id: lock.php $
 */

class Lock
{
    private $_lockObjectList = [];

    /**
     * 加锁
     * @param  string  $areaSign  区域标识
     * @param  bool
     */
    public function lock($areaSign){
        if(isset($this->_lockObjectList[$areaSign])){
            return FALSE;
        }
        $lockObj = new \Swoole\Lock(SWOOLE_MUTEX);
        $result = $lockObj->trylock();
        if($result === TRUE){
            $this->_lockObjectList[$areaSign] = $lockObj;
        }
        return $result;
    }

    /**
     * 解锁
     * @param  string  $areaSign  区域标识
     * @return bool
     */
    public function unLock($areaSign){
        if(!isset($this->_lockObjectList[$areaSign])){
            return FALSE;
        }
        $lockObj = $this->_lockObjectList[$areaSign];
        return $lockObj->unlock();
    }

    /**
     * 全部解锁
     * @param  void
     * @return void
     */
    public function unLockAll(){
        if(!empty($this->_lockObjectList)){
            foreach($this->_lockObjectList as $lock){
                $lock->unlock();
            }
        }
    }
}