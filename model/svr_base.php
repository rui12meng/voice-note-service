<?php
namespace Model;

/**
 * 服务基类
 * $Id: svr_base.php $
 * @author mengrui
 */
class SvrBase //extends \Lsf\Model
{
    /**
     * @var mixed
     */
    private $_curl;
    /**
     * @var array
     */
    private $_configUrls = [];
    /**
     * @var mixed
     */
    private $groupName;
    /**
     * @var string
     */
    private $_domain = '';
    /**
     * @var int
     */
    private $_timeout = 0;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        //parent::__construct();
        $this->_curl       = \Lsf\Loader::plugin('Curl');
        $this->_configUrls = \Lsf\Loader::config('rpc_url', true, 2);
    }

    /**
     * curl get
     * @param  string    $urlKey
     * @param  int       $headerGroup 1-usercenter 2-order
     * @param  array     $params      GET参数
     * @return array|int -1 curl_error -2 http_code_error -3 接口响应数据json解析失败
     */

    protected function get($urlKey, $headerGroup, $params = [])
    {
        $this->_setGlobalHeader($headerGroup);
        $this->_setGroupConfig($headerGroup);
        $pathUrl  = $this->_domain . '/' . $this->_configUrls[$this->groupName][$urlKey];
        if ( ! empty($params)) {
            $pathUrl = $pathUrl . (strpos($pathUrl, '?') === false ? '?' : '') . http_build_query($params, '', '&');
        }
        $response = $this->_curl->get($pathUrl, $this->_timeout);
        if ($this->_curl->errCode) {
            \Lsf\Loader::plugin('Log')->error(9040503, [
                'api_path'   => $this->_configUrls[$this->groupName][$urlKey],
                'curl_errno' => $this->_curl->errCode,
            ]);

            return -101;
        }
        // http_code_error
        if ($this->_curl->httpCode != 200) {
            \Lsf\Loader::plugin('Log')->error(9040502, [
                'api_path'  => $this->_configUrls[$this->groupName][$urlKey],
                'http_code' => $this->_curl->httpCode,
            ]);

            return -102;
        }
        $data = json_decode($response, true);
        if (json_last_error() > 0) {
            \Lsf\Loader::plugin('Log')->error(9040504, [
                'api_path'  => $this->_configUrls[$this->groupName][$urlKey],
                'response'  => $response,
                'errno'     => json_last_error(),
                'error_msg' => json_last_error_msg(),
            ]);

            return -103;
        }

        return $data;
    }

    /**
     * curl post
     * @param  string    $urlKey
     * @param  array     $params
     * @param  array     $headers
     * @param  int       $headerGroup 1-usercenter 2-order
     * @return array|int -1 curl_error -2 http_code_error -3 接口响应数据json解析失败
     */
    protected function post($urlKey, $params, $headers=[], $headerGroup = 1)
    {
        $this->_setGlobalHeader($headerGroup , $headers);
        $this->_setGroupConfig($headerGroup);
        $pathUrl  = $this->_domain . '/' . $this->_configUrls[$this->groupName][$urlKey];

        if(empty($params)){
            $postData = json_encode((object)$params , JSON_UNESCAPED_UNICODE);
        }else{
            $postData = json_encode($params , JSON_UNESCAPED_UNICODE);
        }
        $response = $this->_curl->post($pathUrl, $postData, $this->_timeout);

        /**
         * curl_errno
         * [1] => 'CURLE_UNSUPPORTED_PROTOCOL',
         * [2] => 'CURLE_FAILED_INIT',
         * [3] => 'CURLE_URL_MALFORMAT',
         * [4] => 'CURLE_URL_MALFORMAT_USER',
         * [5] => 'CURLE_COULDNT_RESOLVE_PROXY',
         * [6] => 'CURLE_COULDNT_RESOLVE_HOST',
         * [7] => 'CURLE_COULDNT_CONNECT',
         * [8] => 'CURLE_FTP_WEIRD_SERVER_REPLY',
         * [9] => 'CURLE_REMOTE_ACCESS_DENIED',
         * [11] => 'CURLE_FTP_WEIRD_PASS_REPLY',
         * [13] => 'CURLE_FTP_WEIRD_PASV_REPLY',
         * [14] =>'CURLE_FTP_WEIRD_227_FORMAT',
         * [15] => 'CURLE_FTP_CANT_GET_HOST',
         * [17] => 'CURLE_FTP_COULDNT_SET_TYPE',
         * [18] => 'CURLE_PARTIAL_FILE',
         * [19] => 'CURLE_FTP_COULDNT_RETR_FILE',
         * [21] => 'CURLE_QUOTE_ERROR',
         * [22] => 'CURLE_HTTP_RETURNED_ERROR',
         * [23] => 'CURLE_WRITE_ERROR',
         * [25] => 'CURLE_UPLOAD_FAILED',
         * [26] => 'CURLE_READ_ERROR',
         * [27] => 'CURLE_OUT_OF_MEMORY',
         * [28] => 'CURLE_OPERATION_TIMEDOUT',
         * [30] => 'CURLE_FTP_PORT_FAILED',
         * [31] => 'CURLE_FTP_COULDNT_USE_REST',
         * [33] => 'CURLE_RANGE_ERROR',
         * [34] => 'CURLE_HTTP_POST_ERROR',
         * [35] => 'CURLE_SSL_CONNECT_ERROR',
         * [36] => 'CURLE_BAD_DOWNLOAD_RESUME',
         * [37] => 'CURLE_FILE_COULDNT_READ_FILE',
         * [38] => 'CURLE_LDAP_CANNOT_BIND',
         * [39] => 'CURLE_LDAP_SEARCH_FAILED',
         * [41] => 'CURLE_FUNCTION_NOT_FOUND',
         * [42] => 'CURLE_ABORTED_BY_CALLBACK',
         * [43] => 'CURLE_BAD_FUNCTION_ARGUMENT',
         * [45] => 'CURLE_INTERFACE_FAILED',
         * [47] => 'CURLE_TOO_MANY_REDIRECTS',
         * [48] => 'CURLE_UNKNOWN_TELNET_OPTION',
         * [49] => 'CURLE_TELNET_OPTION_SYNTAX',
         * [51] => 'CURLE_PEER_FAILED_VERIFICATION',
         * [52] => 'CURLE_GOT_NOTHING',
         * [53] => 'CURLE_SSL_ENGINE_NOTFOUND',
         * [54] => 'CURLE_SSL_ENGINE_SETFAILED',
         * [55] => 'CURLE_SEND_ERROR',
         * [56] => 'CURLE_RECV_ERROR',
         * [58] => 'CURLE_SSL_CERTPROBLEM',
         * [59] => 'CURLE_SSL_CIPHER',
         * [60] => 'CURLE_SSL_CACERT',
         * [61] => 'CURLE_BAD_CONTENT_ENCODING',
         * [62] => 'CURLE_LDAP_INVALID_URL',
         * [63] => 'CURLE_FILESIZE_EXCEEDED',
         * [64] => 'CURLE_USE_SSL_FAILED',
         * [65] => 'CURLE_SEND_FAIL_REWIND',
         * [66] => 'CURLE_SSL_ENGINE_INITFAILED',
         * [67] => 'CURLE_LOGIN_DENIED',
         * [68] => 'CURLE_TFTP_NOTFOUND',
         * [69] => 'CURLE_TFTP_PERM',
         * [70] => 'CURLE_REMOTE_DISK_FULL',
         * [71] => 'CURLE_TFTP_ILLEGAL',
         * [72] => 'CURLE_TFTP_UNKNOWNID',
         * [73] => 'CURLE_REMOTE_FILE_EXISTS',
         * [74] => 'CURLE_TFTP_NOSUCHUSER',
         * [75] => 'CURLE_CONV_FAILED',
         * [76] => 'CURLE_CONV_REQD',
         * [77] => 'CURLE_SSL_CACERT_BADFILE',
         * [78] => 'CURLE_REMOTE_FILE_NOT_FOUND',
         * [79] => 'CURLE_SSH',
         * [80] => 'CURLE_SSL_SHUTDOWN_FAILED',
         * [81] => 'CURLE_AGAIN',
         * [82] => 'CURLE_SSL_CRL_BADFILE',
         * [83] => 'CURLE_SSL_ISSUER_ERROR',
         * [84] => 'CURLE_FTP_PRET_FAILED',
         * [84] => 'CURLE_FTP_PRET_FAILED',
         * [85] => 'CURLE_RTSP_CSEQ_ERROR',
         * [86] => 'CURLE_RTSP_SESSION_ERROR',
         * [87] => 'CURLE_FTP_BAD_FILE_LIST',
         * [88] => 'CURLE_CHUNK_FAILED');
         */
        // curl_error
        if ($this->_curl->errCode) {
            \Lsf\Loader::plugin('Log')->error(9040503, [
                'api_path'   => $this->_configUrls[$this->groupName][$urlKey],
                'curl_errno' => $this->_curl->errCode,
            ]);

            return -101;
        }
        // http_code_error
        if ($this->_curl->httpCode != 200) {
            \Lsf\Loader::plugin('Log')->error(9040502, [
                'api_path'  => $this->_configUrls[$this->groupName][$urlKey],
                'http_code' => $this->_curl->httpCode,
            ]);

            return -102;
        }
        $result = [];
        if(isset($response['body'])){
            $result['body'] = json_decode($response['body'], true);
        }
        if(isset($response['headers'])){
            $result['headers'] = $response['headers'];
        }
        //$data = json_decode($response, true);
        if (json_last_error() > 0) {
            \Lsf\Loader::plugin('Log')->error(9040504, [
                'api_path'  => $this->_configUrls[$this->groupName][$urlKey],
                'response'  => $response,
                'errno'     => json_last_error(),
                'error_msg' => json_last_error_msg(),
            ]);

            return -103;
        }

        return $result;
    }

    /**
     * 设置通用HEADER
     * @param  int    $headerGroup
     * @param  string $headers
     * @return void
     */
    private function _setGlobalHeader($headerGroup, $headers=[])
    {
        if(isset($headers) && !empty($headers)){
            foreach ($headers as $k => $v){
                if(!empty($v)){
                    $this->_curl->setHeader($k, $v);
                }
            }
        }
        if ($headerGroup == 3) {
            $this->_curl->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        } else {
            $this->_curl->setHeader('Content-Type', 'application/json');
        }
    }

    /**
     * 设置组配置
     * @param  int          $type
     * @throws \Exception
     * @return void
     */
    private function _setGroupConfig($type)
    {
        switch ($type) {
            // 火山ASR服务
            case 1:
                $groupName = 'svr_volc';
                $config = 'SVR_VOLC_';
                break;
            case 2: // 火山AI TEXT服务
                $groupName = 'svr_volc_text';
                $config = 'SVR_VOLC_TEXT_';
                break;
            case 3: // 阿里云OCR识别服务
                $groupName = 'svr_ali_ocr';//ali_cloud_ocr
                $config = 'SVR_ALI_CLOUD_OCR_';
                break;

//            case 3:
//                $groupName = '';
//                $config = '';
//                break;
        }
        $groupConfig = \Lsf\Env::group($config);
        if (empty($groupConfig)) {
            \Lsf\Loader::plugin('Log')->error(9040505, ['group_name' => $groupName]);
            throw new \Exception('ConfigCenter custom group ' . $groupName . ' not exists');
        } elseif ( ! isset($groupConfig['domain']) || empty($groupConfig['domain'])) {
            \Lsf\Loader::plugin('Log')->error(9040507, ['group_name' => $groupName]);
            throw new \Exception('ConfigCenter custom group ' . $groupName . ' key domain not exists');
        } elseif ( ! isset($groupConfig['timeout']) || empty($groupConfig['timeout'])) {
            \Lsf\Loader::plugin('Log')->error(9040508, ['group_name' => $groupName]);
            throw new \Exception('ConfigCenter custom group ' . $groupName . ' key timeout not exists');
        } else {
            $this->groupName = $groupName;
            $this->_domain   = $groupConfig['domain'];
            $this->_timeout  = $groupConfig['timeout'];
        }
    }
}
