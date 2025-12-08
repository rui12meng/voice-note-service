<?php
namespace Lsf\Plugin;

/**
 * 日志插件类
 * @author mr
 * $Id: log.php $
 */

class Log
{
    private $_debug = FALSE;
    private $_config = [];
    private $_errnoMsgConfig = [];
    private $_logPath = '';
    private $_logFile = '';
    private $_level = [
        'trace' => '', // 追踪定位或记录一些自定义数据
        'debug' => '', // 进行诊断性帮助的信息
        'info' => '', // 系统记录日志，服务启动、停止、配置加载等
        'warning' => '', // 可能导致服务或应用程序异常的记录，不影响程序的正常执行，需要予以关注
        'error' => '', // 对操作而非服务或应用程序造成的致命错误，程序可继续执行，通常需要介入解决
        'fatal' => '', // 致命错误，程序无法正常执行，直接导致服务或应用程序关闭
    ];

    /**
     * 构造函数
     * @param  array  $config
     * @return void
     */
    public function __construct($config){
        // 初始化先加载框架日志错误码
        $this->_errnoMsgConfig = include_once(dirname(dirname(__FILE__)) . '/config/log_message.php');
        // 支持定义日志目录
        if(isset($config['dir']) && !empty($config['dir'])){
            $this->_logPath = $config['dir'];
            // 默认路径
        }else{
            $this->_logPath = WEBPATH . '/log';
        }
        // 自动创建目录
        if(is_dir($this->_logPath)){
            if(!is_writeable($this->_logPath) && !chmod($this->_logPath, 0755)){
                throw new \Exception('Log path ' . $this->_logPath . ' unwriteable');
            }
        }else{
            mkdir($this->_logPath, 0755, TRUE);
        }
        // debug
        if(isset($config['debug'])){
            $this->_debug = $config['debug'];
        }
        $this->_config = $config;
    }

    /**
     * 设置错误码配置信息
     * @param  array  $config
     * @return void
     */
    public function setErrorMsgConfig($config){
        if(is_array($config) || !empty($config)){
            $this->_errnoMsgConfig = $this->_errnoMsgConfig + $config;
        }
    }

    /**
     * __call
     * @param  string  $method
     * @param  array   $args
     * @return bool
     */
    public function __call($method, $args){
        // 检查参数
        if(array_key_exists($method, $this->_level) === FALSE || !is_array($args)){
            return FALSE;
        }
        // debug过滤
        if($this->_debug === FALSE && $method == 'debug'){
            return TRUE;
        }
        // 日志文件名
        $fileName = !empty($this->_config['filename_prefix']) ? $this->_config['filename_prefix'] : 'project';
        $dateStr = date('Ymd');
        $logFilePath = $this->_logPath . '/' . $fileName . '.log.' . $dateStr;
        $logErrorFilePath = $this->_logPath . '/' . $fileName . '.log.wf.' . $dateStr;
        // 初始化级别对应文件
        foreach($this->_level as $level => &$filePath){
            $filePath = in_array($level, array('warning', 'error', 'fatal')) ? $logErrorFilePath : $logFilePath;
        }
        // 定义日志文件
        $this->_logFile = $this->_level[$method];
        // 错误码
        if(empty($args[0])){
            $errno = $errnoMsg = '';
        }else{
            $errno = $args[0];
            $errnoMsg = !empty($this->_errnoMsgConfig[$errno]) ? $this->_errnoMsgConfig[$errno] : 'undefine';
        }
        // Tag
        $tag = !empty($args[2]) ? $args[2] : 'default';
        // 自定义内容块
        $custom = '';
        $tmpArr = array();
        if(!empty($args[1]) && is_array($args[1])){
            foreach($args[1] as $key => $value){
                if(is_array($value)){
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $tmpArr[] = $key . '=' . $value;
            }
            $custom = implode('||', $tmpArr);
        }
        // debug_backtrace
        $debugBackTraceInfo = debug_backtrace();
        $debugBackTraceStr = '';
        if(
            isset($debugBackTraceInfo[0]['file'])
            && isset($debugBackTraceInfo[0]['class'])
            && isset($debugBackTraceInfo[0]['function'])
            && isset($debugBackTraceInfo[0]['line'])
        ){
            $debugBackTraceStr.= 'file=' . $debugBackTraceInfo[0]['file'] . ' ';
            $debugBackTraceStr.= 'line=' . $debugBackTraceInfo[0]['line'] . ' ';
            $debugBackTraceStr.= 'class=' . $debugBackTraceInfo[0]['class'] . ' ';
            $debugBackTraceStr.= 'function=' . $debugBackTraceInfo[0]['function'];
        }
        // 毫秒
        list($usec, $sec) = explode(' ', microtime());
        $msec = sprintf("%'03d", $usec * 1000);
        // 格式化日志字符串
        $str = '';
        $str.= '[' . strtoupper($method) .']'; // level
        $str.= '[' . date('Y-m-d H:i:s', $sec) . '.' . $msec . ']'; // time
        $str.= '[' . $debugBackTraceStr . ']'; // debug_backtrace
        $str.= '[tag=' . $tag . ']'; // tag
        $str.= $errno ? '[errno=' . $errno . ']' : ''; // 错误码
        $str.= $errnoMsg ? '[errno_msg=' . $errnoMsg . ']' : ''; // 错误信息
        $str.= $custom ? '[' . $custom . ']' : ''; // 自定义内容块
        $workerId = \Lsf\Core::$php->getWorkerId();
        if($workerId !== ''){
            $str.= '[worker_id=' . $workerId . ']';
        }
        $workerType = \Lsf\Core::$php->getWorkerType();
        if($workerType !== ''){
            $str.= '[worker_type=' . ($workerType == 1 ? 'normal' : 'task')  . ']';
        }
        $requestId = \Lsf\Core::$php->getRequestId();
        if($requestId){
            $str.= '[request_id=' . $requestId . ']';
        }
        $str.= "\n";
        $this->_write($str);
        return TRUE;
    }

    /**
     * 写文件
     * @param  string  $str
     * @return void
     */
    private function _write($str){
        if(is_dir($this->_logPath)){
            $fd = @fopen($this->_logFile, 'a+');
            if(is_resource($fd)){
                fputs($fd, $str);
                fclose($fd);
            }
        }
    }
}