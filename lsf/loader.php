<?php
namespace Lsf;

/**
 * 加载器操作类
 * @author mr
 * $Id: loader.php $
 */

class Loader
{
    static protected $namespaces = [];
    static protected $model = [];
    static protected $service = [];
    static private $_configPath = '/config/';
    static private $_modelPath = '/model/';
    static private $_servicePath = '/service/';
    static protected $config = [];
    static protected $plugin = [];
    static protected $module = [];

    /**
     * 自动载入类
     * @param  string  $class
     * @return void
     */
    static public function autoload($class){
        $root = explode('\\', trim($class, '\\'), 2);
        if(count($root) > 1 && isset(self::$namespaces[$root[0]])){
            $m = explode('\\', $root[1]);
            $class = array_pop($m);
            $namespace = empty($m) ? '' : implode('/', $m);
            if(empty($namespace)){
                $filePath = self::$namespaces[$root[0]] . '/' . uncamelize($class) . '.php';
            }else{
                $filePath = self::$namespaces[$root[0]] . '/' . uncamelize($namespace) . '/' .  uncamelize($class) . '.php';
            }
            if(is_file($filePath) === FALSE){
                throw new \Exception('Class ' . $class . ' Autoload file ' . $filePath . ' not found, filepath: ' . $filePath);
            }
            include_once($filePath);
        }
    }

    /**
     * 设置根命名空间
     * @param  string  $root
     * @param  string  $path
     * @return void
     */
    static public function addNameSpace($root, $path){
        self::$namespaces[$root] = $path;
    }

    /**
     * 加载Config
     * @param  string  $name
     * @param  bool    $forceRootDir
     * @param  int     $fileForamt    配置文件格式（1-php 2-ini）
     * @param  string  $appName       应用名称
     * @return object
     */
    static public function config($name, $forceRootDir = FALSE, $fileFormat = 1, $appName = ''){
        if($forceRootDir === FALSE && $appName == ''){
            throw new \Exception('Loader config ' . $name . ' not force root dir but not define param app_name');
        }
        $appSign = $appName == '' ? 'default' : $appName;
        $key = $forceRootDir === TRUE ? $name . '_root' : $name . '_' . $appSign;
        if(isset(self::$config[$key])){
            return self::$config[$key];
            // 首次加载
        }else{
            switch($fileFormat){
                case 1:
                    $ext = '.php';
                    break;
                case 2:
                    $ext = '.ini';
                    break;
                default:
                    throw new \Exception('config param file_format value invalid, value: '. $fileFormat);
            }

            if($forceRootDir === TRUE){
                mark:

                $filePath = WEBPATH . self::$_configPath . $name . $ext;
                $config = load_file_config($filePath, $fileFormat);
                if($config !== FALSE){
                    self::$config[$key] = $config;
                    return $config;
                }else{
                    throw new \Exception('Force use root dir config fail, file ' . $name . ' not found');
                }

            }

            $filePath = APPPATH . '/' . $appName . self::$_configPath . $name . $ext;
            $config = load_file_config($filePath, $fileFormat);
            if($config !== FALSE){
                self::$config[$key] = $config;
                return $config;
            }else{
                goto mark;
            }
        }
        throw new \Exception('Config ' . $name . ' not found');
    }

    /**
     * Model加载
     * @param  string  $name
     * @param  bool    $forceRootDir
     * @param  string  $appName
     * @return object
     */
    static public function model($name, $forceRootDir = FALSE, $appName = ''){
        if($forceRootDir === FALSE && $appName == ''){
            throw new \Exception('Loader model ' . $name . ' not force root dir but not define param app_name');
        }
        $appSign = $appName === FALSE ? 'default' : $appName;
        $key = $forceRootDir === TRUE ? $name . '_root' : $name . '_' . $appSign;
        // 已经加载过直接返回
        if(isset(self::$model[$key])){
            return self::$model[$key];
            // 首次加载
        }else{
            // 强制使用根目录model
            if($forceRootDir === TRUE){
                mark:
                $class = '\\Model\\' . $name;
                $model = new $class();
                self::$model[$key] = $model;
                return $model;
            }
            // 非强制则优先选择当前app下的model目录
            // $appName = \Lsf\Core::$php->getEnv('app');
            // 获取失败
            // if(empty($appName)){
            //     throw new \Exception('getEnv app name fail');
            // }
            // 带应用目录的命名空间不适用autoload
            $filePath = APPPATH . '/' . $appName . self::$_modelPath .  uncamelize($name) . '.php';
            include_once($filePath);
            $class = '\\' . camelize($appName) . '\\Model\\' . $name;
            $model = new $class();
            self::$model[$key] = $model;
            return $model;
        }
    }

    /**
     * Service加载
     * @param  string  $name
     * @param  bool    $forceRootDir
     * @param  string  $appName
     * @return object
     */
    static public function service($name, $forceRootDir = FALSE, $appName = ''){
        if($forceRootDir === FALSE && $appName == ''){
            throw new \Exception('Loader service ' . $name . ' not force root dir but not define param app_name');
        }
        $appSign = $appName === FALSE ? 'default' : $appName;
        $key = $forceRootDir === TRUE ? $name . '_root' : $name . '_' . $appSign;
        // 已经加载过直接返回
        if(isset(self::$service[$key])){
            return self::$service[$key];
            // 首次加载
        }else{
            // 强制使用根目录service
            if($forceRootDir === TRUE){
                mark:
                $class = '\\Service\\' . $name;
                $service = new $class();
                self::$service[$key] = $service;
                return $service;
            }
            // 带应用目录的命名空间不适用autoload
            $filePath = APPPATH . '/' . $appName . self::$_servicePath .  uncamelize($name) . '.php';
            include_once($filePath);
            $class = '\\' . camelize($appName) . '\\Service\\' . $name;
            $service = new $class();
            self::$service[$key] = $service;
            return $service;
        }
    }

    /**
     * 加载模块
     * @param  string  $name
     * @param  int     $key
     * @param  bool    $singleton
     * @return object
     */
    static public function module($name, $key = 0, $singleton = TRUE){
        if(empty($name)){
            throw new \Exception('Module name empty');
        }
        $sign = $name . '_' . $key;
        if(!isset(self::$module[$sign]) || $singleton === FALSE){
            $class = '\\Lsf\\Factory\\' . $name;
            $module = $class::getInstance($key, $singleton);
            if($singleton === TRUE){
                self::$module[$sign] = $module;
            }else{
                return $module;
            }
        }
        return self::$module[$sign];
    }

    /**
     * 加载插件
     * @param  string  $name
     * @param  array   $initParams
     * @return object
     */
    static public function plugin($name, $initParams = []){
        if(empty($name)){
            throw new \Exception('plugin name empty');
        }
        // 已经加载过直接返回
        if(isset(self::$plugin[$name])){
            return self::$plugin[$name];
            // 首次加载
        }else{
            $class = '\\Lsf\\Plugin\\' . $name;
            if(count($initParams) > 0){
                $plugin = new $class($initParams);
            }else{
                $plugin = new $class();
            }
            self::$plugin[$name] = $plugin;
            return $plugin;
        }
    }
}