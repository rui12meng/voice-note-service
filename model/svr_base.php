<?php
namespace Model;

/**
 * 服务基类
 * $Id: svr_base.php $
 * @author mengrui
 */
class SvrBase extends \Lsf\Model
{
    /**
     * @var mixed
     */
    private $_curl;
    /**
     * @var mixed
     */
    private $_configUrls;
    /**
     * @var mixed
     */
    private $groupName;
    /**
     * @var array
     */
    private $_urls = [];
    /**
     * @var string
     */
    private $_domain = '';
    /**
     * @var int
     */
    private $_timeout = 0;

    /**
     * @var array
     */
    protected $_paramsData = [];

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_curl       = \Lsf\Loader::plugin('Curl');
        $this->_configUrls = \Lsf\Loader::config('rpc_url', true, 2);
    }

    /**
     * curl post_rpc
     * @param  string    $urlkey
     * @param  array     $params
     * @param  int       $headerGroup 1-usercenter 2-order
     * @return array|int -1 curl_error -2 http_code_error -3 接口响应数据json解析失败
     */
    protected function post_asr($pathurl, $params, $header)
    {
        foreach($header as $headerK => $headerV){
            // 跳过空键名
            if (empty($headerK)) {
                continue;
            }

            // 处理数组值（转换为字符串）
            if (is_array($headerV)) {
                $headerV = implode(', ', $headerV);
            }

            // 跳过空值
            if ($headerV === null || $headerV === '') {
                continue;
            }

            // 确保键名是字符串
            $headerK = (string)$headerK;
            $this->_curl->setHeader( $headerK, $headerV );
        }

        if(empty($params)){
            $postData = json_encode((object)$params);
        }else{
            $postData = json_encode($params);
        }

        $response = $this->_curl->post($pathurl, $postData, $this->_timeout);

        if ($this->_curl->errCode) {
            \Lsf\Loader::plugin('Log')->error(9040503, [
                'api_path'   => $pathurl,
                'curl_errno' => $this->_curl->errCode,
            ]);

            return -101;
        }
        // http_code_error
        if ($this->_curl->httpCode != 200) {
            \Lsf\Loader::plugin('Log')->error(9040502, [
                'api_path'  => $pathurl,
                'http_code' => $this->_curl->httpCode,
            ]);

            return -102;
        }
        $data = json_decode($response, true);
        if (json_last_error() > 0) {
            \Lsf\Loader::plugin('Log')->error(9040504, [
                'api_path'  => $pathurl,
                'response'  => $response,
                'errno'     => json_last_error(),
                'error_msg' => json_last_error_msg(),
            ]);

            return -103;
        }

        return $data;
    }

    /**
     * curl get
     * @param  string    $urlkey
     * @param  int       $headerGroup 1-usercenter 2-order
     * @param  array     $params      GET参数
     * @return array|int -1 curl_error -2 http_code_error -3 接口响应数据json解析失败
     */

    protected function get($urlkey, $headerGroup, $params = [])
    {
        $this->_setGlobalHeader($headerGroup);
        $this->_setGroupConfig($headerGroup);
        $pathurl = $this->_domain . '/' . $this->_urls[$urlkey];
        if ( ! empty($params)) {
            $pathurl = $pathurl . (strpos($pathurl, '?') === false ? '?' : '') . http_build_query($params, '', '&');
        }
        $response = $this->_curl->get($pathurl, $this->_timeout);
        if ($this->_curl->errCode) {
            \Lsf\Loader::plugin('Log')->error(9040503, [
                'api_path'   => $this->_urls[$urlkey],
                'curl_errno' => $this->_curl->errCode,
            ]);

            return -101;
        }
        // http_code_error
        if ($this->_curl->httpCode != 200) {
            \Lsf\Loader::plugin('Log')->error(9040502, [
                'api_path'  => $this->_urls[$urlkey],
                'http_code' => $this->_curl->httpCode,
            ]);

            return -102;
        }
        $data = json_decode($response, true);
        if (json_last_error() > 0) {
            \Lsf\Loader::plugin('Log')->error(9040504, [
                'api_path'  => $this->_urls[$urlkey],
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
     * @param  string    $urlkey
     * @param  array     $params
     * @param  int       $headerGroup 1-usercenter 2-order
     * @return array|int -1 curl_error -2 http_code_error -3 接口响应数据json解析失败
     */
    protected function post($urlkey, $params, $headerGroup = 1)
    {
        $this->_setGlobalHeader($headerGroup);
        $this->_setGroupConfig($headerGroup);
        $pathurl  = $this->_domain . '/' . $this->_urls[$urlkey];
        $postData = $headerGroup == 4 || $headerGroup == 7 ? http_build_query($params) : json_encode($params);
        if ($headerGroup == 10) {
            $this->_curl->setHeader('Accept-Encoding', 'gzip');
            $postData = gzencode(json_encode($params));
        }
        $response = $this->_curl->post($pathurl, $postData, $this->_timeout);
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
                'api_path'   => $this->_urls[$urlkey],
                'curl_errno' => $this->_curl->errCode,
            ]);

            return -101;
        }
        // http_code_error
        if ($this->_curl->httpCode != 200) {
            \Lsf\Loader::plugin('Log')->error(9040502, [
                'api_path'  => $this->_urls[$urlkey],
                'http_code' => $this->_curl->httpCode,
            ]);

            return -102;
        }
        $data = json_decode($response, true);
        if (json_last_error() > 0) {
            \Lsf\Loader::plugin('Log')->error(9040504, [
                'api_path'  => $this->_urls[$urlkey],
                'response'  => $response,
                'errno'     => json_last_error(),
                'error_msg' => json_last_error_msg(),
            ]);

            return -103;
        }

        return $data;
    }

    /**
     * curl get_rpc
     * @param  string    $urlkey
     * @param  int       $headerGroup 1-usercenter 2-order
     * @param  array     $params      GET参数
     * @return array|int -1 curl_error -2 http_code_error -3 接口响应数据json解析失败
     */

    protected function get_rpc($urlkey, $params = [], $headerGroup = 1)
    {
        $this->_setGlobalHeader($headerGroup);
        $this->_setGroupConfig($headerGroup);
        $pathurl = $this->_domain . '/' . $this->_configUrls[$this->groupName][$urlkey];
        if ( ! empty($params)) {
            $pathurl = $pathurl . (strpos($pathurl, '?') === false ? '?' : '') . http_build_query($params, '', '&');
        }
        $response = $this->_curl->get($pathurl, $this->_timeout);
        if ($this->_curl->errCode) {
            \Lsf\Loader::plugin('Log')->error(9040503, [
                'api_path'   => $this->_configUrls[$this->groupName][$urlkey],
                'curl_errno' => $this->_curl->errCode,
            ]);

            return -101;
        }
        // http_code_error
        if ($this->_curl->httpCode != 200) {
            \Lsf\Loader::plugin('Log')->error(9040502, [
                'api_path'  => $this->_configUrls[$this->groupName][$urlkey],
                'http_code' => $this->_curl->httpCode,
            ]);

            return -102;
        }
        $data = json_decode($response, true);
        if (json_last_error() > 0) {
            \Lsf\Loader::plugin('Log')->error(9040504, [
                'api_path'  => $this->_configUrls[$this->groupName][$urlkey],
                'response'  => $response,
                'errno'     => json_last_error(),
                'error_msg' => json_last_error_msg(),
            ]);

            return -103;
        }

        return $data;
    }

    /**
     * curl post_rpc
     * @param  string    $urlkey
     * @param  array     $params
     * @param  int       $headerGroup 1-usercenter 2-order
     * @return array|int -1 curl_error -2 http_code_error -3 接口响应数据json解析失败
     */
    protected function post_rpc($urlkey, $params, $headerGroup = 1)
    {
        $this->_setGlobalHeader($headerGroup);
        $this->_setGroupConfig($headerGroup);
        $pathurl  = $this->_domain . '/' . $this->_configUrls[$this->groupName][$urlkey];
        $postData = $headerGroup == 4 || $headerGroup == 7 ? http_build_query($params) : json_encode($params);
        if ($headerGroup == 10) {
            $this->_curl->setHeader('Accept-Encoding', 'gzip');
            $postData = gzencode(json_encode($params));
        }
        $response = $this->_curl->post($pathurl, $postData, $this->_timeout);
        if ($this->_curl->errCode) {
            \Lsf\Loader::plugin('Log')->error(9040503, [
                'api_path'   => $this->_configUrls[$this->groupName][$urlkey],
                'curl_errno' => $this->_curl->errCode,
            ]);

            return -101;
        }
        // http_code_error
        if ($this->_curl->httpCode != 200) {
            \Lsf\Loader::plugin('Log')->error(9040502, [
                'api_path'  => $this->_configUrls[$this->groupName][$urlkey],
                'http_code' => $this->_curl->httpCode,
            ]);

            return -102;
        }
        $data = json_decode($response, true);
        if (json_last_error() > 0) {
            \Lsf\Loader::plugin('Log')->error(9040504, [
                'api_path'  => $this->_configUrls[$this->groupName][$urlkey],
                'response'  => $response,
                'errno'     => json_last_error(),
                'error_msg' => json_last_error_msg(),
            ]);

            return -103;
        }

        return $data;
    }

    /**
     * 设置通用HEADER
     * @param  void
     * @return void
     */
    private function _setGlobalHeader($headerGroup)
    {
        if ($headerGroup == 4 || $headerGroup == 7 || $headerGroup == 11) {
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
            // 用户服务
            case 1:
                $groupName = 'svr_user';
                break;
            // 发送验证码
            case 3:
                $groupName = 'svr_smscode';
                break;
            // 小鹅通
            case 4:
                $groupName = 'svr_xiaoetongapi';
                break;
            // oms
            case 5:
                $groupName = 'svr_oms';
                break;
            // 应用中心
            case 6:
                $groupName = 'svr_appcenter';
                break;
            //oauth 验证中心
            case 7:
                $groupName = 'svr_oauth';
                break;
            // 观象台
            case 8:
                $groupName = 'svr_stu';
                break;
            // 应用中心
            case 9:
                $groupName = 'svr_pms';
                break;
            // 上报日志
            case 10:
                $groupName = 'svr_logreport';
                break;
            // tool-web直播课服务
            case 11:
                $groupName = 'svr_toolweb';
                break;
            // 会员中心
            case 12:
                $groupName = 'svr_member';
                break;
            // 会员中心
            case 13:
                $groupName = 'svr_toolkit';
                break;
            // OCR
            case 14:
                $groupName = 'svr_ocr';
                break;
            // ailearn 智能批改
            case 15:
                $groupName = 'svr_ailearn';
                break;
            // resource 资源中心
            case 16:
                $groupName = 'svr_resource';
                break;
            // 教学工具
            case 17:
                $groupName = 'svr_teachtool';
                break;
            // 统一资源上传
            case 19:
                $groupName = 'svr_uploads';
                break;
            // ailearn 智能批改(教工)
            case 20:
                $groupName = 'svr_aifreestyle';
                break;
            // markwrong 错号识别
            case 21:
                $groupName = 'svr_mark';
                break;
            case 22:
                $groupName = 'svr_msg';
                break;
            case 23:
                $groupName = 'svr_device';
                break;
            case 24:
                $groupName = 'svr_im';
                break;
            case 25:
                $groupName = 'svr_productcenter';
                break;
            //oauth 验证中心 new,新的传参方式
            case 26:
                $groupName = 'svr_oauthnew';
                break;
            case 27:
                $groupName = 'svr_bizops';
                break;
        }
        $groupConfig = \Lsf\Loader::plugin('ConfigCenter')->group($groupName);
        if (empty($groupConfig)) {
            \Lsf\Loader::plugin('Log')->error(9040505, ['group_name' => $groupName]);
            throw new \Exception('ConfigCenter custom group ' . $groupName . ' not exists');
        } elseif ( ! isset($groupConfig['urls']) || empty($groupConfig['urls'])) {
            \Lsf\Loader::plugin('Log')->error(9040506, ['group_name' => $groupName]);
            throw new \Exception('ConfigCenter custom group ' . $groupName . ' key urls not exists');
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
            $this->_urls     = json_decode($groupConfig['urls'], true);
            if (json_last_error() > 0) {
                \Lsf\Loader::plugin('Log')->error(9040509, [
                    'group_name' => $groupName,
                    'urls'       => $groupConfig['urls'],
                    'errno'      => json_last_error(),
                    'error'      => json_last_error_msg(),
                ]);
                throw new \Exception('ConfigCenter custom group ' . $groupName . ' key urls json decode fail');
            }
        }
    }
}
