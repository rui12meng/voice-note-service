<?php
namespace Lsf;

/**
 * 路由操作类
 * @author mr
 * $Id: router.php $
 */

class Router
{
    const DEFAULT_REQUEST_TYPE = 'dynamic';

    private $_requestType = '';
    private $_pathPrefix = '';
    private $_defaultUri = [
        'app'           => 'home',
        'controller'    => 'page',
        'action'        => 'index'
    ];

    /**
     * 设置默认路由
     * @param  string  $app
     * @param  string  $controller
     * @param  string  $action
     * @return void
     */
    public function setDefaultRouter($app, $controller, $action){
        $this->_defaultUri['app'] = $app;
        $this->_defaultUri['controller'] = $controller;
        $this->_defaultUri['action'] = $action;
    }

    /**
     * 设置路径前缀
     * @param  string  $pathPrefix
     * @return void
     */
    public function setPathPrefix($pathPrefix){
        $this->_pathPrefix = $pathPrefix;
    }

    /**
     * 解析uri
     * @param  string  $uri
     * @return mixed
     */
    public function parseUri($uri){
        $this->_requestType = self::DEFAULT_REQUEST_TYPE;
        if(empty($uri)){
            return $this->_defaultUri;
        }
        $uriArr = explode('/', $uri);
        if(count($uriArr) > 0){
            $lastIndexValue = end($uriArr);
            $last = explode('.', $lastIndexValue);
            // 文件
            if(isset($last[1])){
                $this->_requestType = 'file';
                return $this->_defaultUri;
            }
            // 动态请求处理
            $router = [];
            $tmpArr = explode('/', $uri);
            // 配置了路径前缀且当前为四级uri则忽略路径前缀
            if($this->_pathPrefix != '' && isset($tmpArr[3])){
                // 匹配成功则需要过滤
                if(isset($tmpArr[0]) && $tmpArr[0] == $this->_pathPrefix){
                    unset($tmpArr[0]);
                    $uriArr = array_values($tmpArr);
                    // 未匹配成功直接使用默认uri
                }else{
                    return $this->_defaultUri;
                }
            }
            // 非标准三级结构都使用默认路由
            if(count($uriArr) > 3){
                throw new \Exception('Request uri support three level at most');
            }
            // check app name
            if(!empty($uriArr[0]) && !$this->_checkUriValue($uriArr[0])){
                throw new \Exception('Uri parse error, app name not illegal');
                // check controller name
            }elseif(isset($uriArr[1]) && !empty($uriArr[1]) && !$this->_checkUriValue($uriArr[1])){
                throw new \Exception('Uri parse error, controller name not illegal');
                // check action name
            }elseif(isset($uriArr[2]) && !empty($uriArr[2]) && !$this->_checkUriValue($uriArr[2])){
                throw new \Exception('Uri parse error, action name not illegal');
            }else{
                $router['app'] = $uriArr[0];
                $router['controller'] = isset($uriArr[1]) ? $uriArr[1] : $this->_defaultUri['controller'];
                $router['action'] = isset($uriArr[2]) ? $uriArr[2] : $this->_defaultUri['action'];
                return $router;
            }
        }
        return $this->_defaultUri;
    }

    /**
     * 验证路径合法性
     * @param  string  $value
     * @return bool
     */
    private function _checkUriValue($value){
        return preg_match('/^[a-z0-9_]+$/i', $value);
    }

    /**
     * 获取请求类型
     * @param  void
     * @return string
     */
    public function getRequestType(){
        return $this->_requestType;
    }
}