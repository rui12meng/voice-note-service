<?php
namespace Lsf;

/**
 * Lsf框架核心类
 * @author
 * $Id: core.php $
 */

class Core
{
    static public $php;
    public $router;
    private $_server;
    private $_request               = [];
    private $_response              = [];
    private $_env                   = [];
    private $_requestCommonParams   = [];
    private $_workerInfo            = [];

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    private function __construct(){
        $this->router = new \Lsf\Router();
    }

    /**
     * 单例统一访问入口
     * @param  void
     * @return object
     */
    static public function getInstance(){
        if(!self::$php){
            self::$php = new Core();
        }
        return self::$php;
    }

    /**
     * 设置请求和响应对象
     * @param  object  $request
     * @param  object  $response
     * @return void
     */
    public function setRequestResponse($request, $response){
        // 记录request_in日志
        $requestLogData = [];
        if(isset($request->header)){
            $requestLogData['header'] = $request->header;
        }
        if(isset($request->server)){
            $requestLogData['server'] = $request->server;
        }
        if(isset($request->get)){
            $requestLogData['get'] = $request->get;
        }
        if(isset($request->post)){
            $requestLogData['post'] = $request->post;
        }
        if(isset($request->cookie)){
            $requestLogData['cookie'] = $request->cookie;
        }
        if(isset($request->raw_content)){
            $requestLogData['raw_content'] = $request->raw_content;
        }
        if(isset($request->files)){
            $requestLogData['files'] = $request->files;
        }
        // 获取请求染色id
        if(isset($request->header['requestid']) && !empty($request->header['requestid'])){
            $this->setRequestCommonParams('request_id', $request->header['requestid']);
        }else{
            $this->setRequestCommonParams('request_id', getUqId());
        }
        $apiGzip = 0;

        //files文件客户端以数组形式传参；不会统一处理成json且不压缩；由于端上传参api-gzip=1 与类型不符；对于该特殊数据类型；默认统一不压缩流程处理（针对文件上传；单独处理）
        if(isset($request->header['content-type']) && strpos($request->header['content-type'],'multipart/form-data')!== FALSE){
            $request->header['api-gzip'] = 0;
        }

        // 兼容json数据格式的post请求
        // if(isset($request->header['content-type']) && preg_match('/json/i', $request->header['content-type'])){
        // 压缩
        if($request->rawContent() != FALSE){
            if($apiGzip == 1 || (isset($request->header['api-gzip']) && $request->header['api-gzip'] == 1)){
                $gzData = gzdecode($request->rawContent());
                if($gzData != FALSE){
                    $post = json_decode($gzData, TRUE);
                    if(json_last_error() > 0){
                        \Lsf\Loader::plugin('Log')->error(9000013, ['raw_content' => $gzData], LSF_ERROR_TAG);
                        throw new \Exception('Request rawcontent json invalid');
                    }
                }else{
                    \Lsf\Loader::plugin('Log')->error(9000012, ['raw_content' => $request->rawContent()], LSF_ERROR_TAG);
                    throw new \Exception('Request rawcontent gzdecode fail');
                }
            }else{
                if(isset($request->header['content-type']) && strpos($request->header['content-type'],'multipart/form-data')!== FALSE){
                    $post = array_merge($request->files, $request->post);
                }else{
                    $post = json_decode($request->rawContent(), TRUE);
                    if(json_last_error() > 0){
                        \Lsf\Loader::plugin('Log')->error(9000013, ['raw_content' => $request->rawContent()], LSF_ERROR_TAG);
                        throw new \Exception('Request rawcontent json invalid');
                    }
                }

            }
            // 获取客户端版本号
            if(isset($post['vc']) && !empty($post['vc'])){
                $this->setRequestCommonParams('version', $post['vc']);
            }
            // 获取客户端设备id
            if(isset($post['udid']) && !empty($post['udid'])){
                $this->setRequestCommonParams('device_id', $post['udid']);
            }
            // 获取客户端系统类型
            if(isset($post['os']) && !empty($post['os'])){
                $this->setRequestCommonParams('os', strtolower($post['os']));
            }
            else{
                $this->setRequestCommonParams('os', 'brower');
            }
            $request->post = $post;
            $requestLogData['post'] = $post;
        }
        // }
        // 协程id
        $cid = $this->getCid();
        $requestLogData['coroutine_id'] = $cid;
        // 进程内当前协程数量
        $coroutineStatus = \Swoole\Coroutine::stats();
        if(isset($coroutineStatus['coroutine_num'])){
            $requestLogData['coroutine_num'] = $coroutineStatus['coroutine_num'];
        }
        $this->_request[$cid] = $request;
        $this->_response[$cid] = $response;
        \Lsf\Loader::plugin('Log')->info('', $requestLogData, 'request_in');
    }

    /**
     * 执行请求
     * @param  string  $uri
     * @param  int     $source  0-外部 1-内部
     * @return mixed
     */
    public function runRequest($uri, $source = 0){
        // router解析
        $runInfo = $this->router->parseUri(trim($uri, '/'));
        if(empty($runInfo)){
            throw new \Exception('Server request_uri parse fail, uri: ' . $uri);
            // 暂时不支持文件访问
        }elseif($this->router->getRequestType() == 'file'){
            throw new \Exception('Server not support file type request');
            // 内部错误
        }elseif(empty($runInfo['app']) || empty($runInfo['controller']) || empty($runInfo['action'])){
            throw new \Exception('Server request_uri parse result error, uri: ' . $uri);
            // 禁止调用
        }elseif($source == 0 && substr($runInfo['action'], 0, 5) == 'inner'){
            throw new \Exception('Server request_uri forbidden call, uri: ' . $uri);
            // router解析成功
        }else{
            $class = '\\' . camelize($runInfo['app']) . '\\Controller\\' . camelize($runInfo['controller']);
            $filePath = APPPATH . '/' . $runInfo['app'] . '/controller/' . $runInfo['controller'] . '.php';
            $action = camelize($runInfo['action'], 0);
            if(is_file($filePath)){
                include_once($filePath);
            }else{
                throw new \Exception('Controller file not found, path ' . $filePath);
            }
            // 类不存在
            if(class_exists($class, FALSE) === FALSE){
                throw new \Exception($class . ' not exists');
            }
            // 在env中注册
            // $this->registerEnv('app', $runInfo['app']);
            // $this->registerEnv('controller', $runInfo['controller']);
            // $this->registerEnv('action', $action);
            // 设置日志错误码信息
            $errorMsgGlobalConfig = \Lsf\Loader::config('log_message', TRUE);
            $errorMsgAppConfig = \Lsf\Loader::config('log_message', FALSE, 1, $runInfo['app']);
            // 保证应用下重复键不覆盖全局配置
            $errorMsgConfig = $errorMsgGlobalConfig + $errorMsgAppConfig;
            \Lsf\Loader::plugin('Log')->setErrorMsgConfig($errorMsgConfig);
            $object = new $class($runInfo['app'], $runInfo['controller'], $runInfo['action']);
            if(method_exists($object, $action) === FALSE){
                throw new \Exception('Controller ' . $class . '->' . $action . ' not found');
            }
            return $object->$action();
        }
    }

    /**
     * 获取request对象
     * @param  void
     * @return void
     */
    public function getRequest(){
        $cid = $this->getCid();
        return isset($this->_request[$cid]) ? $this->_request[$cid] : new \stdClass();
    }

    /**
     * 获取response对象
     * @param  void
     * @return void
     */
    public function getResponse(){
        $cid = $this->getCid();
        return isset($this->_response[$cid]) ? $this->_response[$cid] : new \stdClass();
    }

    /**
     * 设置server对象
     * @param  object  $server
     * @return void
     */
    public function setServer($server){
        $this->_server = $server;
    }

    /**
     * 获取server对象
     * @param  void
     * @return object
     */
    public function getServer(){
        return $this->_server;
    }

    /**
     * 设置环境标识
     * @param  void
     * @return void
     */
    public function setEnvironment($environment){
        $this->registerEnv('environment', $environment);
    }

    /**
     * 注册环境变量
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function registerEnv($key, $value){
        if(empty($key) || empty($value)){
            throw new \Exception('Env register key or value empty');
        }
        $this->_env[$key] = $value;
    }

    /**
     * 获取环境变量
     * @param  string  $key
     * @return mixed
     */
    public function getEnv($key){
        if(isset($this->_env[$key])){
            return $this->_env[$key];
        }else{
            return FALSE;
        }
    }

    /**
     * 设置workerId
     * @param  int   $workerId
     * @param  int   $workerType  1-普通 2-任务
     * @return void
     */
    public function setWorkerInfo($workerId, $workerType){
        $this->_workerInfo['worker_id'] = $workerId;
        $this->_workerInfo['worker_type'] = $workerType;
    }

    /**
     * 获取workerId
     * @param  void
     * @return int
     */
    public function getWorkerId(){
        return isset($this->_workerInfo['worker_id']) ? $this->_workerInfo['worker_id'] : '';
    }

    /**
     * 获取worker类型
     * @param  void
     * @return int
     */
    public function getWorkerType(){
        return isset($this->_workerInfo['worker_type']) ? $this->_workerInfo['worker_type'] : '';
    }

    /**
     * 设置请求公共参数
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    public function setRequestCommonParams($key, $value){
        $cid = $this->getCid();
        $this->_requestCommonParams[$cid][$key] = $value;
    }

    /**
     * 清理请求数据
     * @param  void
     * @return void
     */
    public function clearRequestData(){
        $cid = $this->getCid();
        unset($this->_request[$cid]);
        unset($this->_response[$cid]);
        unset($this->_requestCommonParams[$cid]);
    }

    /**
     * 获取request_id
     * @param  void
     * @return string
     */
    public function getRequestId(){
        $cid = $this->getCid();
        return isset($this->_requestCommonParams[$cid]['request_id']) ? $this->_requestCommonParams[$cid]['request_id'] : '';
    }

    /**
     * 获取客户端版本号
     * @param  void
     * @return string
     */
    public function getClientVersion(){
        $cid = $this->getCid();
        return isset($this->_requestCommonParams[$cid]['version']) ? $this->_requestCommonParams[$cid]['version'] : '';
    }

    /**
     * 获取客户端设备编号
     * @param  void
     * @return string
     */
    public function getDeviceId(){
        $cid = $this->getCid();
        return isset($this->_requestCommonParams[$cid]['device_id']) ? $this->_requestCommonParams[$cid]['device_id'] : '';
    }

    /**
     * 获取客户端系统
     * @param  void
     * @return string
     */
    public function getClientOS(){
        $cid = $this->getCid();
        return isset($this->_requestCommonParams[$cid]['os']) ? $this->_requestCommonParams[$cid]['os'] : '';
    }

    /**
     * 获取协程id
     * @param  void
     * @return int
     */
    public function getCid(){
        $cid = \Swoole\Coroutine::getCid();
        return $cid < 0 ? 0 : $cid;
    }

}