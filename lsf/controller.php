<?php
namespace Lsf;

/**
 * 控制器基类
 * @author
 * $Id: controller.php $
 */

class Controller
{
    public $xssClean;
    public $appName         = '';
    public $controllerName  = '';
    public $actionName      = '';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct($appName, $controllerName, $actionName){
        $this->xssClean         = new \Lib\XssClean();
        $this->appName          = $appName;
        $this->controllerName   = $controllerName;
        $this->actionName       = $actionName;
    }

    /**
     * 初始化业务基类
     * @param  void
     * @return void
     */
    public function initAppsApplication(){
        // 自动执行每个apps下的application
        if(!empty($this->appName)){
            $class = '\\App\\' . $this->appName . '\\Application';
            if(class_exists($class) === FALSE){
                throw new \Exception($class . ' not found');
            }else{
                $object = new $class($this);
                $object->_init();
            }
        }else{
            throw new \Exception('Controller construct function app name empty');
        }
    }

    /**
     * 获取get参数
     * @param  string  $index
     * @return mixed
     */
    public function get($index = '', $xssClean = FALSE){
        $request = \Lsf\Core::$php->getRequest();
        if(!$index && !empty($request->get)){
            foreach(array_keys($request->get) as $key){
                $get[$key] = $this->_checkParams($request->get, $key, $xssClean);
            }
            return $get;
        }else{
            return $this->_checkParams($request->get, $index, $xssClean);
        }
    }

    /**
     * 获取post参数
     * @param  string  $index
     * @return mixed
     */
    public function post($index = '', $xssClean = FALSE){
        $request = \Lsf\Core::$php->getRequest();
        if(!$index && !empty($request->post)){
            foreach(array_keys($request->post) as $key){
                $post[$key] = $this->_checkParams($request->post, $key, $xssClean);
            }
            return $post;
        }else{
            return $this->_checkParams($request->post, $index, $xssClean);
        }
    }

    /**
     * 获取files参数
     * @param  string  $index
     * @return mixed
     */
    public function files($index = '', $xssClean = FALSE){
        $request = \Lsf\Core::$php->getRequest();
        if(!$index && !empty($request->files)){
            foreach(array_keys($request->files) as $key){
                $post[$key] = $this->_checkParams($request->files, $key, $xssClean);
            }
            return $post;
        }else{
            return $this->_checkParams($request->files, $index, $xssClean);
        }
    }

    /**
     * 统一格式输出json
     * @param  int     $eCode
     * @param  array   $data
     * @param  string  $eMsg
     * @param  bool    $changObject
     * @param  bool    $assignHeader 是否指定header头Content-Type
     * @return string  $json
     */
    public function json($eCode = 0, $data = [], $eMsg = '', $changObject=TRUE , $assignHeader = TRUE){
        // 定义json数组
        if($changObject === TRUE){
            $jsonArr = [
                'meta' => [
                    'ecode' => $eCode,
                    'emsg'  => ''
                ],
                'data' => (object)$data
            ];
        }else{
            $jsonArr = [
                'meta' => [
                    'ecode' => $eCode,
                    'emsg'  => ''
                ],
                'data' => $data
            ];
        }
        if($eMsg != ''){
            $jsonArr['meta']['emsg'] = $eMsg;
        }else{
            // 依次加载应用错误码配置
            $msgGlobalConfig = \Lsf\Loader::config('response_message', TRUE);
            $msgAppConfig = \Lsf\Loader::config('response_message', FALSE, 1, $this->appName);
            $msgConfig = $msgGlobalConfig + $msgAppConfig;
            if(empty($msgConfig)){
                throw new \Exception('Response msg config content empty');
            }elseif(!isset($msgConfig[$eCode])){
                throw new \Exception('Response msg config content not define, ecode ' . $eCode);
            }elseif(is_array($msgConfig[$eCode]) && !isset($msgConfig[$eCode]['emsg'])){
                throw new \Exception('Response msg config ecode value is array but key emsg not define, ecode ' . $eCode);
            }elseif(is_array($msgConfig[$eCode]) && !isset($msgConfig[$eCode]['desc'])){
                throw new \Exception('Response msg config ecode value is array but key desc not define, ecode ' . $eCode);
            }else {
                // 生成路由key
                $routerKey = $this->appName . '_' . $this->controllerName . '_' . $this->actionName;
                // 错误信息和描述字段处理（兼容只有emsg的错误提示方式）
                if (is_string($msgConfig[$eCode])) {
                    $jsonArr['meta']['emsg'] = $msgConfig[$eCode];
                } else {
                    $tmpArr = ['emsg' => $msgConfig[$eCode]['emsg']];
                    // 路由匹配
                    $descArr = [];
                    if (isset($msgConfig[$eCode]['desc'][$routerKey])) {
                        $descArr = $msgConfig[$eCode]['desc'][$routerKey];
                    } elseif (isset($msgConfig[$eCode]['desc']['default'])) {
                        $descArr = $msgConfig[$eCode]['desc']['default'];
                    }else{
                        $descArr = [];
                    }
                    // 最多支持三部分定义（保证前置存在才解析下级）
                    if (isset($descArr[0]) && !empty($descArr[0])) {
                        $tmpArr[] = $descArr[0];
                        if (isset($descArr[1]) && !empty($descArr[1])) {
                            $tmpArr[] = $descArr[1];
                            if (isset($descArr[2]) && !empty($descArr[2])) {
                                $tmpArr[] = $descArr[2];
                            }
                        }
                    }
                    $jsonArr['meta']['emsg'] = implode('--', $tmpArr);
                }
            }
        }
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $json = json_encode($jsonArr, JSON_UNESCAPED_UNICODE);
        // 支持jsonp
        $contentType = 'application/json; charset=utf-8';
        if(!empty($_REQUEST['jsonp'])){
            $contentType = 'application/x-javascript; charset=utf-8';
            $json = $_REQUEST['jsonp'] . '(' . $json . ');';
        }
        if($assignHeader === TRUE){
            $this->setHeader('Content-Type', $contentType);
        }
        global $responseStatus, $responseMsg;
        $responseStatus = $eCode;
        $responseMsg = $jsonArr['meta']['emsg'];
        return $json;
    }

    /**
     * 获取header
     * @param  string  $key
     * @return mixed
     */
    public function getHeader($key = ''){
        $request = \Lsf\Core::$php->getRequest();
        if($key){
            return isset($request->header[$key]) ? $request->header[$key] : '';
        }else{
            return $request->header;
        }
    }

    /**
     * 定义header
     * @param  string  $key
     * @param  string  $value
     * @return void
     */
    public function setHeader($key, $value){
        \Lsf\Core::$php->getResponse()->header($key, $value);
    }

    /**
     * 设置http状态码
     * @param  int  $httpCode
     * @return void
     */
    public function setHttpCode($httpCode){
        \Lsf\Core::$php->getResponse()->status($httpCode);
    }

    /**
     * 校验参数
     * @param  array   $array
     * @param  string  $index
     * @param  bool    $xssClean
     * @return mixed
     */
    private function _checkParams(&$array, $index = '', $xssClean = FALSE){
        if(!isset($array[$index])){
            return FALSE;
        }
        if($xssClean === TRUE){
            return $this->xssClean->xss_clean($array[$index]);
        }
        return $array[$index];
    }

}
