<?php
namespace Lsf;

/**
 * 性能检测操作类
 * @author
 * $Id: performance.php $
 */

class Performance
{
    /**
     * 计算处理时间
     * @param  float   $startTime  mictotime(TRUE)
     * @return string
     */
    static public function countRunTime($startTime){
        return self::formatTime(microtime(TRUE) - $startTime);
    }

    /**
     * 格式化时间
     * @param  float   $time
     * @return string
     */
    static public function formatTime($time){
        return round($time, 5) . 's';
    }

    /**
     * 计算内存使用
     * @param  void
     * @return string
     */
    static public function countMemoryUse(){
        $memoryBytes = memory_get_usage();
        if($memoryBytes >= 1073741824){
            $memory = round($memoryBytes / 1073741824 * 100) / 100 . 'Gb';
        }elseif($memoryBytes >= 1048576){
            $memory = round($memoryBytes / 1048576 * 100) / 100 . 'Mb';
        }elseif($memoryBytes >= 1024){
            $memory = round($memoryBytes / 1024 * 100) / 100 . 'Kb';
        }else{
            $memory = $memoryBytes . 'Bytes';
        }
        return $memory;
    }
}