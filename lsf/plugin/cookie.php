<?php
namespace Lsf\Plugin;

/**
 * Cookie插件类
 * @author mr
 * $Id: cookie.php $
 */

class Cookie
{
    public $path = '/';
    public $domain = '';
    public $secure = FALSE;
    public $httponly = FALSE;

    /**
     * __construct
     * @param  array  $config
     * @return void
     */
    public function __construct($config){
        if(isset($config['path']) && !empty($config['path'])){
            $this->path = $config['path'];
        }
        if(isset($config['domain']) && !empty($config['domain'])){
            $this->domain = $config['domain'];
        }
        if(isset($config['secure']) && !empty($config['secure'])){
            $this->secure = $config['secure'];
        }
        if(isset($config['httponly']) && !empty($config['httponly'])){
            $this->httponly = $config['httponly'];
        }
    }

    /**
     * set path
     * @param  string  $path
     * @return void
     */
    public function setPath($path){
        $this->path = $path;
    }

    /**
     * set domain
     * @param  string  $domain
     * @return void
     */
    public function setDomain($domain){
        $this->domain = $domain;
    }

    /**
     * set secure
     * @param  bool  $secure
     * @return void
     */
    public function setSecure($secure){
        $this->secure = $secure;
    }

    /**
     * set httponly
     * @param  bool  $httponly
     * @return void
     */
    public function setHttpOnly($httponly){
        $this->httponly = $httponly;
    }

    /**
     * get
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = NULL){
        if(!isset(\Lsf\Core::$php->request->cookie[$key])){
            return $default;
        }else{
            return \Lsf\Core::$php->request->cookie[$key];
        }
    }

    /**
     * set
     * @param  string  $key
     * @param  string  $value
     * @param  int     $expire
     * @return void
     */
    public function set($key, $value, $expire = 0){
        if($expire != 0){
            $expire = time() + $expire;
        }
        \Lsf\Core::$php->response->cookie(
            $key,
            $value,
            $expire,
            $this->path,
            $this->domain,
            $this->$secure,
            $this->$httponly
        );
    }

    /**
     * delete
     * @param  string  $key
     * @return void
     */
    static function delete($key){
        \Lsf\Core::$php->response->cookie($key, '', time()-3600, Cookie::$path, Cookie::$domain, Cookie::$secure, Cookie::$httponly);
    }
}