<?php
namespace Lsf\Plugin;

/**
 * Shard插件类
 * @author mr
 * $Id: shard.php $
 */

class Shard
{
    private $_shardAutoSerialKey = '';
    private $_shardAutoSerialIntKey = '';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_shardAutoSerialKey = 'global_serial_generator_seed_127.0.0.1';
        $this->_shardAutoSerialIntKey = crc32($this->_shardAutoSerialKey);
    }

    /**
     * 获取核心虚拟节点id
     * @param  void
     * @return int
     */
    public function getCoreVsid(){
        return 1;
    }

    /**
     * 生成新模式全局序列id
     * @param  int  $id
     * @param  int  $flag
     * @return int  $id
     */
    public function makeSerialId($id){
        if(empty($id) || !is_numeric($id)){
            throw new \Exception('makeSerialId param not illegal, id must be not empty and is numeric');
        }
        // 是否为全局id
        if($this->_isGlobalSerialId($id)){
            $vsid = $this->extractVirtShardId($id);
        }else{
            $vsid = $id;
        }
        // 获得自增数字
        $autoInc = $this->_getNextValueByLocalFile();
        $id = $this->_generateNewId($autoInc, $vsid);
        return $id;
    }

    /**
     * 反解析虚拟节点id
     * @param  int  $id
     * @return int
     */
    public function extractVirtShardId($id){
        if(empty($id) || !is_numeric($id)){
            throw new \Exception('extractVirtShardId param not illegal, id must be not empty and is numeric');
        }
        // 只要是全局序列id反解析规则相同
        if($this->_isGlobalSerialId($id)){
            return $id >> 10 & (0xFF);
        }else{
            throw new \Exception('extractVirtShardId param id not illegal global serial id');
        }
    }

    /**
     * 通过本地文件来生成一个auto_increment序列，
     * @param  void
     * @return int   $nextval
     */
    private function _getNextValueByLocalFile(){
        $file = WEBPATH . '/' . $this->_shardAutoSerialKey;
        $fp = fopen($file, 'c+');
        if(!$fp){
            throw new \Exception('generate auto inc local file open fail');
        }
        $nextValue = 0;
        fseek($fp, 0, SEEK_SET);
        if(flock($fp, LOCK_EX)){
            $nextValue = fread($fp, 32);
            fseek($fp, 0, SEEK_SET);
            if(empty($nextValue)){
                $nextValue = 1;
                fwrite($fp, $nextValue);
            }else{
                $nextValue = (int)$nextValue + 1;
                $nvl = strlen($nextValue);
                fwrite($fp, "{$nextValue}", $nvl);
                ftruncate($fp, $nvl);
            }
        }else{
            fclose($fp);
            throw new \Exception('generate auto inc local file flock fail');
        }
        fclose($fp);
        return $nextValue;
    }

    /**
     * 是否是全局序列id
     * @param  int   $id
     * @return bool
     */
    private function _isGlobalSerialId($id){
        return $id > 255;
    }

    /**
     * 是否是全新全局序列id
     * @param  int  $id
     * @return bool
     */
    private function _isNewId($id){
        // 使用最小生成单位生成进行判断
        $minVsid = $this->getCoreVsid();
        return $id > $this->_generateNewId(1024, $minVsid);
    }

    /**
     * 是否是兼容模式全局序列id
     * @param  int  $id
     * @return bool
     */
    private function _isCompatibleId($id){

    }

    /**
     * 生成全新id算法
     * @param  int  $autoInc    自增数字
     * @param  int  $vsid       虚拟节点id
     * @param  int  $reserver   保留位
     * @param  int  $timeStamp  时间戳
     * @return int  $id
     */
    private function _generateNewId($autoInc, $vsid, $reserve = 0x0, $timeStamp = 0){
        // 生成规则
        // 0-9    mod        10bits
        // 10-17  vsid        8bits
        // 18-57  timestamp  40bits
        // 58-61  reserve     4bits
        // 62-63  version     2bits（01）
        if($autoInc < 0){
            throw new \Exception('Generate 64B id param not illegal, autoinc must be greater than 0');
        }elseif($vsid < 0 || $vsid > 255){
            throw new \Exception('Generate 64B id param not illegal, vsid ranage must be 0 - 255');
        }elseif($reserve < 0 || $reserve > 15){
            throw new \Exception('Generate 64B id param not illegal, reserve ranage must be 0 - 15');
        }elseif($timeStamp < 0 || $timeStamp > 1099511627775){
            throw new \Exception('Generate 64B id param not illegal, timestamp ranage must be 0 - 1099511627775');
        }else{
            // 当前时间距离时间起始点的毫秒时间戳
            $timeStamp = $timeStamp ? $timeStamp : (microtime(TRUE) - mktime(0, 0, 0, 7, 3, 2019)) * 1000;
            // 取模
            $mod = $autoInc % 1024;
            $id = $mod << 0 | $vsid << 10 | $timeStamp << 18 | $reserve << 58 | 0x1 << 62;
            return $id;
        }
    }

    /**
     * 生成兼容id算法
     * @param  int  $oldId      老id
     * @param  int  $vsid       虚拟节点id
     * @param  int  $reserver   保留位
     * @return int  $id
     */
    private function _generateCompatibleId($oldId, $vsid, $reserve = 0x0){
        // 生成规则
        // 0-7    vsid        8bits
        // 8-55   oldid       48bits
        // 56-57  zero        2bits
        // 58-61  reserve     4bits
        // 62-63  version     2bits（00）
        if(!$oldId){
            throw new \Exception('Generate 64B id param not illegal, old_id must be greater than 0');
        }elseif($vsid < 0 || $vsid > 255){
            throw new \Exception('Generate 64B id param not illegal, vsid ranage must be 0 - 255');
        }elseif($reserve < 0 || $reserve > 15){
            throw new \Exception('Generate 64B id param not illegal, reserve ranage must be 0 - 15');
        }else{
            $id = $vsid << 0 | $oldId << 8 | 0x0 << 56 | $reserve << 58 | 0x0 << 62;
            return $id;
        }
    }
}