<?php
namespace Lsf\Server;

use Lsf\Env;

/**
 * WEB服务器类
 * @author mr
 * $Id: web_server.php $
 */

class WebServer extends \Lsf\Protocol\HttpServer
{
    private $_server;
    private $_lsfConfig     = [];
    private $_serverConfig  = [];
    private $_logConfig     = [];
    private $_crontabConfig = [];
    private $_swooleConfig  = [
        'worker_num'    => 4,
        'max_request'   => 0,
        'dispatch_mode' => 3,
        'daemonize'     => 0
    ];

    /**
     * 构造函数
     * @param  string  $ip
     * @param  int     $port
     * @param  array   $serverConfig
     * @param  array   $swooleConfig
     * @param  array   $crontabConfig
     * @return void
     */
    public function __construct($ip, $port, $serverConfig = [], $swooleConfig = [], $crontabConfig = []){
        // 生成服务对象
        $this->_server = new \Swoole\Http\Server($ip, $port);
        // 初始化框架配置
        $this->_lsfConfig = \Lsf\Loader::config('lsf', TRUE);
        // 处理swoole配置（相同配置进行覆盖）
        if(!empty($swooleConfig)){
            foreach($swooleConfig as $key => $value){
                $this->_swooleConfig[$key] = $value;
            }
        }
        // 检查swoole配置
        if(!isset($this->_swooleConfig['log_file']) || empty($this->_swooleConfig['log_file'])){
            exit('System swoole config missing log_file');
        }
        // 服务器配置
        if(!empty($serverConfig)){
            // 检查日志配置
            if(!isset($serverConfig['remote']['log.dir']) || empty($serverConfig['remote']['log.dir'])){
                exit('System remote server config missing server@log.dir');
            }
            if(!isset($serverConfig['remote']['log.filename_prefix']) || empty($serverConfig['remote']['log.filename_prefix'])){
                exit('System remote server config missing server@log.filename_prefix');
            }
            $this->_logConfig = [
                'debug'             => $serverConfig['remote']['debug'],
                'dir'               => $serverConfig['remote']['log.dir'],
                'filename_prefix'   => $serverConfig['remote']['log.filename_prefix']
            ];
            // 检查配置中心插件依赖配置（应分配数量大于1个的任务worker）
            if(!isset($swooleConfig['task_worker_num']) || $swooleConfig['task_worker_num'] <= 1){
                exit('System plugin config_center must set task_worker_num more than one');
            }
            // 初始化配置中心锁对象
            $this->_lockList['config_center'] = new \Swoole\Lock(SWOOLE_MUTEX);
            // 保存服务器配置
            $this->_serverConfig = $serverConfig;
        }else{
            exit('System server config empty');
        }
        // crontab配置
        $this->_crontabConfig = $crontabConfig;
        // 配置服务器
        $this->_server->set($swooleConfig);
        // 注册回调方法
        $this->_server->on('start',         [$this, 'onStart']);
        $this->_server->on('workerstart',   [$this, 'onWorkerStart']);
        $this->_server->on('workerstop',    [$this, 'onWorkerStop']);
        $this->_server->on('managerstart',  [$this, 'onManagerStart']);
        $this->_server->on('managerstop',   [$this, 'onManagerStop']);
        $this->_server->on('workererror',   [$this, 'onWorkerError']);
        $this->_server->on('request',       [$this, 'onRequest']);
        $this->_server->on('shutdown',      [$this, 'onShutdown']);
        // task进程
        if(isset($swooleConfig['task_worker_num']) && $swooleConfig['task_worker_num'] > 0){
            $this->_server->on('task',      [$this, 'onTask']);
            $this->_server->on('finish',    [$this, 'onFinish']);
        }
        // 设置捕获php错误方法
        register_shutdown_function([$this, 'onPhpShutDown']);
        set_error_handler([$this, 'onErrorHandler']);
    }

    /**
     * 服务器启动回调函数
     * @param  object  $server
     * @return void
     */
    public function onStart($server){
        // 主进程pid写入文件
        $result = file_put_contents($this->_serverConfig['pid_file_path'], $server->master_pid);
        if($result === FALSE){
            \Lsf\Loader::plugin('Log', $this->_logConfig)->fatal(9000001, ['pid_file_path' => $this->_serverConfig['pid_file_path']], LSF_ERROR_TAG);
        }
    }

    /**
     * manager进程启动回调函数
     * @param  object  $server
     * @return void
     */
    public function onManagerStart($server){
        \Lsf\Loader::plugin('Log', $this->_logConfig)->info('', [], 'swoole_manager_start');
        // 初始化核心类并设置server对象
        \Lsf\Core::getInstance()->setServer($server);
        // 延迟500ms投递任务
        usleep(500000);
        // 配置中心任务注册
        //$server->task('config_center_watch', 0);
    }

    /**
     * manager进程结束回调函数
     * @param  object  $server
     * @return void
     */
    public function onManagerStop($server){
        \Lsf\Loader::plugin('Log')->info('', [], 'swoole_manager_stop');
    }

    /**
     * worker进程启动回调函数
     * @param  object  $server
     * @param  int     $workerId
     * @return void
     */
    public function onWorkerStart($server, $workerId){
        // worker进程内初始化日志插件
        \Lsf\Loader::plugin('Log', $this->_logConfig);
        \Lsf\Loader::plugin('Log')->info('', [], 'swoole_worker_start');
        // 开启PHP网络客户端代码协程化（目前在redis连接池中使用）TODO 后续可考虑优化为直接使用swoole的redis协程客户端
        if(isset($this->_serverConfig['remote']['enable_runtime_coroutine']) && $this->_serverConfig['remote']['enable_runtime_coroutine'] == 1){
            \Swoole\Runtime::enableCoroutine(TRUE, SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_FILE);
        }
        // 初始化核心类并设置server对象
        \Lsf\Core::getInstance();
        \lsf\Core::$php->setServer($server);
        // 设置worker信息
        $workerTypeInt = $server->taskworker === FALSE ? 1 : 2;
        \lsf\Core::$php->setWorkerInfo($workerId, $workerTypeInt);
        // 设置Router相关信息
        if(isset($this->_lsfConfig['router'])){
            $routerConfig = $this->_lsfConfig['router'];
            // 设置路径前缀
            if(isset($routerConfig['path_prefix']) && !empty($routerConfig['path_prefix'])){
                \Lsf\Core::$php->router->setPathPrefix($routerConfig['path_prefix']);
            }
            // 设置默认路由
            if(
                isset($routerConfig['default_app']) && !empty($routerConfig['default_app']) &&
                isset($routerConfig['default_controller']) && !empty($routerConfig['default_controller']) &&
                isset($routerConfig['default_action']) && !empty($routerConfig['default_action'])
            ){
                \Lsf\Core::$php->router->setDefaultRouter($routerConfig['default_app'], $routerConfig['default_controller'], $routerConfig['default_action']);
            }
        }
        // 创建redis连接池
        if(isset($this->_serverConfig['module']['redis']) && $this->_serverConfig['module']['redis'] === TRUE){
            $nodeNameArr = isset($this->_serverConfig['redis']['node_name']) ? $this->_serverConfig['redis']['node_name'] : ['redis'];
            foreach($nodeNameArr as $nodeName){
                //$redisConfig = \Lsf\Loader::plugin('ConfigCenter')->group($nodeName);
                $redisConfig =\Lsf\Env::group('REDIS_');
                \Lsf\Loader::plugin('RedisPool')->createRedisPool($nodeName, $redisConfig);
                \Lsf\Loader::plugin('Log')->error(9000024, $redisConfig, LSF_ERROR_TAG);
            }

//            $redisConfig = \Lsf\Loader::plugin('ConfigCenter')->group('redis');
//            \Lsf\Loader::plugin('RedisPool')->createRedisPool($redisConfig);
        }
        // 创建mysql连接池
        if(isset($this->_serverConfig['module']['mysql']) && $this->_serverConfig['module']['mysql'] === TRUE){
            $nodeNameArr = isset($this->_serverConfig['mysql']['node_name']) ? $this->_serverConfig['mysql']['node_name'] : ['mysql'];
            foreach($nodeNameArr as $nodeName){
               // $mysqlConfig = \Lsf\Loader::plugin('ConfigCenter')->group($nodeName);
                $mysqlConfig =\Lsf\Env::group('DB_');
                \Lsf\Loader::plugin('MysqlPool')->createMysqlPool($nodeName, $mysqlConfig);

                \Lsf\Loader::plugin('Log')->error(9000024, $mysqlConfig, LSF_ERROR_TAG);
            }
        }
        // 非配置中心占用
        if($server->taskworker === TRUE && $this->_swooleConfig['worker_num'] != $workerId){
            // crontab已开启
            if(isset($this->_crontabConfig['switch']) && $this->_crontabConfig['switch'] == 1 &&
                isset($this->_crontabConfig['config']) && !empty($this->_crontabConfig['config'])
            ){
                $crontabInfo = json_decode($this->_crontabConfig['config'], TRUE);
                if(json_last_error() > 0){
                    \Lsf\Loader::plugin('Log')->error(9000024, [
                        'config'    => $this->_crontabConfig['config'],
                        'errno'     => json_last_error(),
                        'error'     => json_last_error_msg(),
                    ], LSF_ERROR_TAG);
                }else{
                    if(!empty($crontabInfo)){
                        foreach($crontabInfo as $sign => $config){
                            // 验证mode和uri
                            if(!isset($config['mode']) || !in_array($config['mode'], [1, 2]) || !isset($config['uri'])){
                                \Lsf\Loader::plugin('Log')->error(9000024, [
                                    'sign'      => $sign,
                                    'param'     => 'mode or uri',
                                    'config'    => $config,
                                ], LSF_ERROR_TAG);
                                continue;
                            }
                            // 固定时间间隔模式下必须设置时间间隔
                            if($config['mode'] == 1 && (!isset($config['time_interval']) || empty($config['time_interval']))){
                                \Lsf\Loader::plugin('Log')->error(9000024, [
                                    'sign'      => $sign,
                                    'param'     => 'time_interval',
                                    'config'    => $config,
                                ], LSF_ERROR_TAG);
                                continue;
                            }
                            // crontab时间表达式模式下必须设置时间表达式
                            if($config['mode'] == 2 && (!isset($config['time_expression']) || empty($config['time_expression']))){
                                \Lsf\Loader::plugin('Log')->error(9000024, [
                                    'sign'      => $sign,
                                    'param'     => 'time_expression',
                                    'config'    => $config,
                                ], LSF_ERROR_TAG);
                                continue;
                            }
                            // 时间间隔根据模式设定
                            $timeInterval = $config['mode'] == 1 ? $config['time_interval'] : 1000;
                            $timerId = \Swoole\Timer::tick($timeInterval, $this->_crontabFunction(), $sign, $config);
                            \Lsf\Loader::plugin('Log')->info('', [
                                'timer_id'  => $timerId,
                                'sign'      => $sign,
                                'config'    => $config,
                            ], 'crontab_register_success');
                        }
                    }
                }
            }
        }
    }

    /**
     * worker进程结束回调函数
     * @param  object  $server
     * @param  int     $workerId
     * @return void
     */
    public function onWorkerStop($server, $workerId){
        $this->_workerEnd();
        \Lsf\Loader::plugin('Log')->info('', [], 'swoole_worker_stop');
    }

    /**
     * worker进程错误回调函数
     * @param  object  $server
     * @param  int     $workerId
     * @param  int     $workerPid
     * @param  int     $exitCode
     * @param  int     $signal
     * @return void
     */
    public function onWorkerError($server, $workerId, $workerPid, $exitCode, $signal){
        $this->_workerEnd();
        $logData = [
            'callback_name' => 'on_worker_error',
            'worker_pid'    => $workerPid,
            'exit_code'     => $exitCode,
            'signal'        => $signal
        ];
        \Lsf\Loader::plugin('Log')->fatal('', $logData, LSF_ERROR_TAG);
    }

    /**
     * worker进程结束处理
     * @param  void
     * @return void
     */
    private function _workerEnd(){
        // 关闭redis连接池
        if(isset($this->_serverConfig['module']['redis']) && $this->_serverConfig['module']['redis'] === TRUE){
            \Lsf\Loader::plugin('RedisPool')->closeRedisPool();
        }
        // 关闭mysql连接池
        if(isset($this->_serverConfig['module']['mysql']) && $this->_serverConfig['module']['mysql'] === TRUE){
            \Lsf\Loader::plugin('MysqlPool')->closeMysqlPool();
        }
    }

    /**
     * 接收请求回调函数
     * @param  object  $request
     * @param  object  $response
     * @return void
     */
    public function onRequest($request, $response){
        $result     = '';
        $startTime  = microtime(TRUE);
        $uri        = $request->server['request_uri'];
        // 请求处理开始
        try{
            // 设置请求和响应对象
            \Lsf\Core::$php->setRequestResponse($request, $response);
            $result = \Lsf\Core::$php->runRequest($uri);
            if(!is_string($result)){
                throw new \Exception('output result must be string');
            }
            // 捕获通过抛出异常实现的的响应内容
        }catch(\Lsf\Exception\FinishException $e){
            $result = $e->getMessage();
            // 捕获异常
        }catch(\Exception $e){
            $response->status(500);
            $result = \Lsf\Error::exceptionPage('Extreme Server Framework exception', $e->getMessage());
            $logData = [
                'callback_name' => 'on_request',
                'exception_msg' => $e->getMessage()
            ];
            \Lsf\Loader::plugin('Log')->fatal(9000003, $logData, LSF_ERROR_TAG);
            // 每次请求结束都要执行
        }finally{
            // 请求结束前需要做的
            $this->_end($uri, $startTime, 0);
            // 响应
            $response->end($result);
        }
    }

    /**
     * 处理完成后操作
     * @param  string  $uri         请求uri
     * @param  int     $startTime   mictotime(TRUE)
     * @param  int     $touchPoint  触发点（0-普通请求处理完成 1-任务处理完成 2-任务回调处理完成 3-定时任务处理完成）
     * @return void
     */
    private function _end($uri, $startTime, $touchPoint = 0){
        $logData = [];
        switch($touchPoint){
            case 0:
                $tag = 'request_out';
                global $responseStatus, $responseMsg;
                if($responseStatus){
                    $logData['response_status'] = $responseStatus;
                }
                if($responseMsg){
                    $logData['response_msg'] = $responseMsg;
                }
                break;
            case 1:
                $tag = 'task_out';
                break;
            case 2:
                $tag = 'task_callback_out';
                break;
            case 3:
                $tag = 'crontab_end';
                break;
            default:
                $tag = '';
        }
        $logData['uri']        = $uri;
        $logData['memory']     = \Lsf\Performance::countMemoryUse();
        $logData['run_time']   = \Lsf\Performance::countRunTime($startTime);
        \Lsf\Loader::plugin('Log')->info('', $logData, $tag);
        // 清理请求数据
        if($touchPoint == 0){
            \Lsf\Core::$php->clearRequestData();
        }
        // 回收连接池资源
        \Lsf\Loader::plugin('RedisPool')->recycle();
        \Lsf\Loader::plugin('MysqlPool')->recycle();
    }

    /**
     * 服务器关闭回调函数
     * @param  object  $server
     * @return void
     */
    public function onShutdown(){
        $result = file_put_contents($this->_serverConfig['pid_file_path'], '');
        if($result === FALSE){
            \Lsf\Loader::plugin('Log')->fatal(9000001, [
                'pid_file_path' => $this->_serverConfig['pid_file_path'],
            ], LSF_ERROR_TAG);
        }
    }

    /**
     * 接收任务回调函数
     * @param  object  $server
     * @param  object  $task
     * @return void
     */
    public function onTask($server, $task){
        $data = $task->data;
        // 配置中心私有约定
//        if($data == 'config_center_watch'){
//            // 监听zookeeper配置
//            \Lsf\Loader::plugin('ConfigCenter')->initWatch();
//            // 500ms
//            while(1){
//                usleep(500000);
//            }
//        }else{
            $startTime = microtime(TRUE);
            // 验证task的约定协议
            // $data = ['task_uri' => '', 'data' => ['request_id' => '123456789']];
            if(
                !isset($data['task_uri']) || empty($data['task_uri']) ||
                !isset($data['data']['request_id']) || empty($data['data']['request_id'])
            ){
                \Lsf\Loader::plugin('Log')->error(9000004, ['data' => $data], LSF_ERROR_TAG);
            }else{
                // 记录task_in日志
                $taskLogData = ['task_id' => $task->id, 'data' => $data];
                \Lsf\Loader::plugin('Log')->info('', $taskLogData, 'task_in');
                // 任务处理开始
                $noException = 0;
                try{
                    // 设置任务参数
                    \Lsf\Loader::plugin('Task')->setTaskParams($data['data']);
                    $result = \Lsf\Core::$php->runRequest($data['task_uri']);
                    $noException = 1;
                }catch(\Lsf\Exception\FinishException $e){
                    $result = $e->getMessage();
                }catch(\Exception $e){
                    $logData = [
                        'callback_name' => 'on_task',
                        'exception_msg' => $e->getMessage()
                    ];
                    \Lsf\Loader::plugin('Log')->fatal(9000005, $logData, LSF_ERROR_TAG);
                    // 每次请求结束都要执行
                }finally{
                    // 请求结束前需要做的
                    $this->_end($startTime, 1);
                    // callback
                    if(!empty($data['callback_uri']) && $noException == 1){
                        $responseData = json_encode([
                            'callback_uri' => $data['callback_uri'],
                            'data' => $result
                        ], JSON_UNESCAPED_UNICODE);
                        $jsonLastError = json_last_error();
                        if($jsonLastError > 0){
                            \Lsf\Loader::plugin('Log')->error(9000006, ['json_last_error' => $jsonLastError], LSF_ERROR_TAG);
                        }else{
                            $server->finish($responseData);
                        }
                    }
                }
            }
        //}
    }

    /**
     * 任务处理完成回调函数
     * @param  object  $server
     * @param  int     $taskId
     * @param  string  $data
     * @return void
     */
    public function onFinish($server, $taskId, $data){
        $startTime = microtime(TRUE);
        $data = json_decode($data, TRUE);
        // 记录task_callback_in日志
        $taskCallbackLogData = ['task_id' => $taskId, 'data' => $data];
        \Lsf\Loader::plugin('Log')->info('', $taskCallbackLogData, 'task_callback_in');
        // 验证task回调的约定协议
        if(!isset($data['callback_uri']) || empty($data['callback_uri']) || !isset($data['data'])){
            \Lsf\Loader::plugin('Log')->error(9000007, ['data' => $data], LSF_ERROR_TAG);
            return FALSE;
        }
        // 任务处理开始
        try{
            // 设置回调参数
            \Lsf\Lib\Task::setCallbackParams($data['data']);
            $result = \Lsf\Core::$php->runRequest($data['callback_uri']);
        }catch(\Lsf\Exception\FinishException $e){
            \Lsf\Loader::plugin('Log')->warning(9000008, ['callback_uri' => $data['callback_uri']], LSF_ERROR_TAG);
        }catch(\Exception $e){
            $logData = [
                'callback_name' => 'on_finish',
                'exception_msg' => $e->getMessage()
            ];
            \Lsf\Loader::plugin('Log')->fatal(9000009, $logData, LSF_ERROR_TAG);
            // 每次请求结束都要执行
        }finally{
            // 请求结束前需要做的
            $this->_end($startTime, 2);
        }
    }

    /**
     * 获取定时任务回调函数
     * @param  void
     * @return function
     */
    private function _crontabFunction(){
        return function($timerId, $sign, $config){
            $isRun = 1;
            // crontab时间表达式验证触发
            if($config['mode'] == 2){
                $isHit = \Lsf\Loader::plugin('Crontab')->timeMatch($config['time_expression']);
                if($isHit === FALSE){
                    $isRun = 0;
                }
            }
            // 允许执行
            if($isRun == 1){
                // 进程内当前协程数量
                $coroutineStatus = \Swoole\Coroutine::stats();
                \Lsf\Loader::plugin('Log')->info('', [
                    'timer_id'      => $timerId,
                    'coroutine_id'  => \Lsf\Core::$php->getCid(),
                    'coroutine_num' => $coroutineStatus['coroutine_num'] ?? 0,
                    'sign'          => $sign,
                    'uri'           => $config['uri'],
                ], 'crontab_start');
                $startTime = microtime(TRUE);
                try{
                    \Lsf\Core::$php->runRequest($config['uri'], 1);
                }catch(\Exception $e){
                    $logData = [
                        'cid'       => \Lsf\Core::$php->getCid(),
                        'sign'      => $sign,
                        'uri'       => $config['uri'],
                        'exception' => $e->getMessage()
                    ];
                    \Lsf\Loader::plugin('Log')->fatal(9000025, $logData, LSF_ERROR_TAG);
                    // 每次请求结束都要执行
                }finally{
                    // 请求结束前需要做的
                    $this->_end($config['uri'], $startTime, 3);
                }
            }
        };
    }

    /**
     * php shutdown
     * [说明]
     * 程序意外退出的致命错误
     * @param  void
     * @return void
     */
    public function onPhpShutDown(){
        $error = error_get_last();
        if(!isset($error['type'])){
            return;
        }
        // 处理指定类型错误
        switch($error['type']){
            case E_ERROR:
                break;
            case E_PARSE:
                break;
            case E_CORE_ERROR:
                break;
            case E_COMPILE_ERROR:
                break;
            default:
                return;
                break;
        }
        $errorLogData = [
            'error_type'    => \Lsf\Error::$errorType[$error['type']],
            'message'       => $error['message'],
            'file'          => $error['file'],
            'line'          => $error['line']
        ];
        \Lsf\Loader::plugin('Log', $this->_logConfig)->fatal(9000010, $errorLogData, LSF_ERROR_TAG);
    }

    /**
     * php error handler
     * [说明]
     * 用户自定义的错误处理程序
     * E_ERROR/E_PARSE/E_CORE_ERROR/E_CORE_WARNING/E_COMPILE_ERROR/E_COMPILE_WARNING不会被处理，error_reporting失效
     * @param  void
     * @return void
     */
    public function onErrorHandler($errno, $errStr, $errFile, $errLine){
        if(!(error_reporting() & $errno)){
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return FALSE;
        }
        switch($errno){
            case E_NOTICE:
                break;
            case E_USER_NOTICE:
                break;
            case E_USER_WARNING:
                break;
            case E_USER_ERROR:
                break;
            default:
                break;
        }
        $errorLogData = [
            'error_type'    => \Lsf\Error::$errorType[$errno],
            'message'       => $errStr,
            'file'          => $errFile,
            'line'          => $errLine
        ];
        \Lsf\Loader::plugin('Log', $this->_logConfig)->error(9000011, $errorLogData, LSF_ERROR_TAG);
        // Don't execute PHP internal error handler
        return TRUE;
    }

    /**
     * 启动
     * @param  void
     * @return void
     */
    public function run(){
        $this->_server->start();
    }

    /**
     * 获取server对象
     * @param  void
     * @return object
     */
    public function server(){
        return $this->_server;
    }

}