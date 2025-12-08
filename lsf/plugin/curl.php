<?php
namespace Lsf\Plugin;

/**
 * Curl插件类
 * @author mr
 * $Id: curl.php $
 */

class Curl
{
    protected $ch;
    protected $userAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:28.0) Gecko/20100101 Firefox/28.0';
    protected $reqHeader = [];
    public $info;
    public $debug = FALSE;
    public $errMsg;
    public $errCode;
    public $httpCode;

    /**
     * 初始化
     * @param  string  $url
     * @return void
     */
    private function _init($url, $timeout = 1){
        // initialize curl handle
        if(!is_resource($this->ch)){
            $this->ch = \curl_init();
        }
        // set error in case http return code bigger than 300
        // curl_setopt($this->ch, CURLOPT_FAILONERROR, TRUE);
        // 在完成交互以后强迫断开连接
        \curl_setopt($this->ch, CURLOPT_FORBID_REUSE, FALSE);
        // allow redirects
        \curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, TRUE);
        // use gzip if possible
        \curl_setopt($this->ch, CURLOPT_ENCODING , 'gzip, deflate');
        // do not veryfy ssl
        // this is important for windows
        // as well for being able to access pages with non valid cert
        \curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        // set url to post to
        \curl_setopt($this->ch, CURLOPT_URL, $url);
        // return into a variable rather than displaying it
        \curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
        // set curl function timeout to $timeout
        \curl_setopt($this->ch, CURLOPT_TIMEOUT, $timeout);
        // ipv4
        \curl_setopt($this->ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        // trace
        $requestId = \Lsf\Core::$php->getRequestId();
        if($requestId){
            $this->setHeader('requestid', $requestId);
        }
        // set header
        $this->setHeader('Connection', 'Keep-Alive');
        $this->setHeader('Keep-Alive', 60);
        \curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->_getHeader());
    }

    /**
     * get
     * @param  string  $pathQuery
     * @param  string  $ip
     * @param  int     $timeout
     * @return mixed
     */
    public function get($url, $timeout = 1){
        $this->_init($url, $timeout);
        // set method to get
        \curl_setopt($this->ch, CURLOPT_HTTPGET, TRUE);
        if(isset($this->reqHeader['User-Agent']) && empty($this->reqHeader['User-Agent'])) {
            \curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
        }
        // write start log
        \Lsf\Loader::plugin('Log')->info('', [
            'http_header'   => $this->_getHeader(),
            'url'           => $url,
            'method'        => 'get'
        ], 'curl_request_start');
        $result = $this->_execute();
        // close connect
        // $this->_close();
        return $result;
    }

    /**
     * post
     * @param  string  $path
     * @param  mixed   $postData
     * @param  string  $ip
     * @param  int     $timeout
     * @return mixed
     */
    public function post($url, $postData, $timeout = 1){
        $this->_init($url, $timeout);
        // bind to specific ip address if it is sent trough arguments
        // if($ip){
        //     \curl_setopt($this->ch, CURLOPT_INTERFACE, $ip);
        // }
        // set method to post
        \curl_setopt($this->ch, CURLOPT_POST, TRUE);
        // generate post string
        $postArray = [];
        if(is_array($postData)){
            foreach($postData as $key => $value){
                $postArray[] = urlencode($key) . '=' . urlencode($value);
            }
            $postString = implode('&', $postArray);
        }else{
            $postString = $postData;
        }
        // set post string
        \curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postString);
        // write start log
        \Lsf\Loader::plugin('Log')->info('', [
            'http_header'   => $this->_getHeader(),
            'url'           => $url,
            'method'        => 'post',
            'post_data'     => $postString
        ], 'curl_request_start');
        $result = $this->_execute();
        // close connect
        // $this->_close();
        return $result;
    }

    /**
     * 执行
     * @param  void
     * @return mixed
     */
    private function _execute(){
        // clear buffer before exec
        $this->_clearBufferBefore();
        // and finally send curl request
        $result = \curl_exec($this->ch);
        // clear buffer after exec
        $this->_clearBufferAfter();
        // after exec logic
        $this->info = \curl_getinfo($this->ch);
        if($this->info){
            $this->httpCode = $this->info['http_code'];
        }
        if(\curl_errno($this->ch)){
            $result         = FALSE;
            $this->errCode  = \curl_errno($this->ch);
            $this->errMsg   = \curl_error($this->ch) . '[' . $this->errCode . ']';
            $logInfo = [
                'curl_errno'    => $this->errCode,
                'curl_error'    => $this->errMsg
            ];
        }else{
            $logInfo = [
                'curl_info'     => $this->info,
                'result'        => $result,
                'run_time'      => \Lsf\Performance::formatTime($this->info['total_time'])
            ];
        }
        \curl_reset($this->ch);
        // write end log
        \Lsf\Loader::plugin('Log')->info('', $logInfo, 'curl_request_end');
        return $result;
    }

    /**
     * 前置清理缓冲区逻辑
     * @param  void
     * @return void
     */
    private function _clearBufferBefore(){
        // 重置错误信息
        $this->errCode = 0;
        $this->errMsg = '';
    }

    /**
     * 后置清理缓冲区逻辑
     * @param  void
     * @return void
     */
    private function _clearBufferAfter(){
        // 重置请求头信息，防止头信息无限变大风险
        $this->reqHeader = [];
    }

    /**
     * 设置header
     * @param  string  $k
     * @param  string  $v
     * @return void
     */
    public function setHeader($k, $v){
        $this->reqHeader[$k] = $v;
    }

    /**
     * 获取header
     * @param  void
     * @return array
     */
    public function _getHeader(){
        $header = [];
        if(!empty($this->reqHeader)){
            foreach($this->reqHeader as $key => $value){
                $header[] = $key . ':' . $value;
            }
        }
        return $header;
    }

    /**
     * 设置Cookie
     * @param  mixed cookie
     * @return void
     */
    public function setCookie($cookie){
        if(is_array($cookie) && !empty($cookie)){
            $cookieArr = $cookie;
            $cookie = '';
            foreach($cookieArr as $name => $value){
                if($name == 'inner_user_info'){
                    $cookie.= $name . '=' . $value . ';';
                }
            }
        }else{
            $cookie = '';
        }
        \curl_setopt($this->ch, CURLOPT_COOKIE, $cookie);
    }

    /**
     * 关闭连接
     * @param  void
     * @return void
     */
    private function _close(){
        // close curl session and free up resources
        \curl_close($this->ch);
    }
}