<?php
namespace App;

require_once LSFPATH . '/lib/php-jwt/autoload.php';

use Lsf\Exception\FinishException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lsf\Env;

/**
 * 应用全局基类
 * $Id: application.php $
 * @author mr
 */

define('ECODE_SUCCESS', 0);                              // 成功
define('ECODE_PARAM_MISSING', 9010000);                  // 缺失参数
define('ECODE_PARAM_VALUE_INVALID', 9010001);            // 参数值非法
define('ECODE_DATABASE_QUERY_FAIL', 9010002);            // 数据库查询失败
define('ECODE_DATA_NOT_FOUND', 9010004);                 // 数据不存在
define('ECODE_API_NETWORK_REQUEST_FAIL', 9010005);       // 接口网络请求失败
define('ECODE_API_RESPONSE_DATA_EXCEPTION', 9010006);    // 接口响应数据异常
define('ECODE_CALL_INNER_METHOD_PARAMS_ERROR', 9010007); // 调用内部方法参数错误
define('ECODE_SEND_SMS_TOO_OFTEN', 9010008);             // 发送短信过于频繁
define('ECODE_SEND_SMS_FAIL', 9010009);                  // 发送短信失败
define('ECODE_UNDEFINED_ERROR', 9010010);                // 未知错误
define('ECODE_API_RESPONSE_CODE_ERROR', 9010011);        // 接口响应错误
define('ECODE_API_PASSPORT_NOT_FOUND', 9010012);         // 账号不存在
define('ECODE_API_PHONE_NUM_ERROR', 9010013);            // 手机号码有误
define('ECODE_DATABASE_INSERT_FAIL', 9010014);           // 数据库存储失败
define('ECODE_UPOLOAD_ERROR', 9010015);                  // 魔拍图片上传失败
define('ECODE_PIC_YELLOW', 9010016);                     // 图片鉴定失败。被鉴定为黄色
define('ECODE_OCR_SUBJECT_ERROR', 9010017);              // 未能识别到学科
define('ECODE_OCR_SUBJECT_INCONFORMITY', 9010018);       // 识别的学科与传入的学科不一致
define('ECODE_OCR_QUESTION_ERROR', 9010020);             // 未能识别到切题标识
define('ECODE_SEND_REQUEST_TOO_OFTEN', 9010021);         // 发送请求过于频繁
define('ECODE_INVALID_QRCODE', 9010100);                 // 请扫描有效二维码
define('ECODE_DEVICE_REPETBIND', 9010123);               // 重复绑定孩子
define('ECODE_STUDENT_REPETBIND', 9010124);              // 孩子重复绑定设备

class Application extends \Lsf\Controller
{
    const SERVER_SWITCH_CODE = 10002;
    /**
     * @var string
     */
    protected $uid = 0; // 用户uid（帐号systemId）
    /**
     * @var string
     */
    protected $token = ''; // 登录态token
    /**
     * @var array
     */
    protected $userInfo = []; // 用户信息
    /**
     * @var array
     */
    protected $noNeedCheckTokenRouter = [ // 无需检查token的路由
        // 帐号
        '/user/oauth/login_or_signup'                    => 1,
        '/user/oauth/token_refresh'                      => 1,
    ];

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);
        // 系统维护（由于之前服务端临时支撑，目前先注释，后续修改方案通过单独接口来维护，而不是全局依赖调用）
        // $this->systemMaintenance();
        $router = '/' . $appName . '/' . $controllerName . '/' . uncamelize($actionName);
        if (array_key_exists($router, $this->noNeedCheckTokenRouter) === false) {
            $this->token = $this->post('token', true);

            if ( ! $this->token) {
                throw new FinishException($this->errParamMissing(ECODE_PARAM_MISSING, 'token'));
            }
            if ($this->token == '') {
                throw new FinishException($this->errParamMissing(ECODE_PARAM_VALUE_INVALID, 'token'));
            }
            //校验token
            //说明：登出不强校验exp，token单独处理（不需要校验 exp 是否过期，即使过期也可以正常退出
            if($router == '/user/oauth/logout'){
                $payload = $this->verifyAccessToken($this->token, ['verify_exp'=> false]);
            }else{
                $payload = $this->verifyAccessToken($this->token);
            }

            if($payload === false){
                throw new FinishException($this->json(9999999, [], 'token非法'));
            }else{
                $this->uid = $payload['sub'];
            }

        }
        // 再执行每个app自定义的初始化方法
        $this->initAppsApplication();
    }

    /**
     * 验证 access_token
     * @param string $token
     * @param arrary $options
     * @return void
     */
    private function verifyAccessToken(string $token, $options = ['verify_exp' => true]) {
        if($options['verify_exp'] === false){
            try{
                $payload = $this->checkTokenIgnoreExp($token);
                return (array)$payload;
            }catch (\Exception $e){
                //log access解析失败，非法token
                return false;
            }

        }else{
            try{
                $payload = JWT::decode($token, new Key(\Lsf\Env::get('TOKEN_JWT_ACCESS_SECRET'), 'HS256'));
                return (array)$payload;
            }catch (\Exception $e) {
                //log access解析失败，非法token
                return false;
            }
        }

    }

    /**
     * 校验token，忽略exp校验，用于退出接口验证（不需要校验 exp 是否过期，即使过期也可以正常退出）
     * @param  string  $token
     * @return void
     */
    private function checkTokenIgnoreExp($token){
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception("Invalid token format");
        }

        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[1]));

        return $payload;
    }

    /**
     * 系统维护
     * @param  void
     * @return void
     */
    private function systemMaintenance()
    {
        $redisKey  = 'plus-okay-middle:system_maintenance';
        $cacheInfo = \Lsf\Loader::plugin('RedisPool')->redis()->get($redisKey);
        if ($cacheInfo === false) {
            $daoOkayServersModel = \Lsf\Loader::model('DaoOkayServers', true);
            $switchInfo          = $daoOkayServersModel->query('status,description', self::SERVER_SWITCH_CODE);
            // 默认开启
            $status      = isset($switchInfo[0]['status']) ? $switchInfo[0]['status'] : 1;
            $description = (isset($switchInfo[0]['description']) && ! empty($switchInfo[0]['description'])) ? $switchInfo[0]['description'] : '';
            $redisValue  = $status;
            // 系统关闭且定义了描述
            if ($status == 0 && $description != '') {
                $redisValue .= '_' . $description;
            }
            // 尝试缓冲一次
            \Lsf\Loader::plugin('RedisPool')->redis()->setex($redisKey, 3600 * 24, $redisValue);
        } else {
            $cacheArr    = explode('_', $cacheInfo);
            $status      = (int) $cacheArr[0];
            $description = isset($cacheArr[1]) ? $cacheArr[1] : '';
        }
        // 系统维护
        if ($status == 0) {
            throw new FinishException($this->json(9999999, [], $description));
        }
    }

    /**
     * 缺失参数
     * @param  int      $eCode
     * @param  string   $paramName
     * @return string
     */
    public function errParamMissing($eCode, $paramName)
    {
        return $this->json($eCode, ['param_name' => $paramName]);
    }

    /**
     * 参数值非法
     * @param  int      $eCode
     * @param  string   $paramName
     * @param  mixed    $value
     * @return string
     */
    public function errParamValueInvalid($eCode, $paramName, $value = '')
    {
        return $this->json($eCode, ['param_name' => $paramName, 'value' => $value]);
    }

    /**
     * 缺失参数------特殊处理数组不转对象
     * @param  int      $eCode
     * @param  string   $paramName
     * @return string
     */
    public function errParamMissingStr($eCode, $paramName, $sign)
    {
        return $this->jsonStr($eCode, ['param_name' => $paramName], '', $sign);
    }

    /**
     * 参数值非法------特殊处理数组不转对象
     * @param  int      $eCode
     * @param  string   $paramName
     * @param  mixed    $value
     * @return string
     */
    public function errParamValueInvalidStr($eCode, $paramName, $value, $sign)
    {
        return $this->jsonStr($eCode, ['param_name' => $paramName, 'value' => $value], '', $sign);
    }

    /**
     * @param  int          $eCode
     * @param  array        $data
     * @param  string       $eMsg
     * @param  bool         $changObject
     * @throws \Exception
     * @return string
     */
    public function jsonStr($eCode = 0, $data = [], $eMsg = '', $changObject = true)
    {
        // 定义json数组
        if ($changObject === true) {
            $jsonArr = [
                'meta' => [
                    'ecode' => $eCode,
                    'emsg'  => '',
                ],
                'data' => (object) $data,
            ];
        } else {
            $jsonArr = [
                'meta' => [
                    'ecode' => $eCode,
                    'emsg'  => '',
                ],
                'data' => $data,
            ];
        }
        if ($eMsg != '') {
            $jsonArr['meta']['emsg'] = $eMsg;
        } else {
            // 依次加载应用错误码配置
            $msgGlobalConfig = \Lsf\Loader::config('response_message', true);
            $msgAppConfig    = \Lsf\Loader::config('response_message', false, 1, $this->appName);

            $msgConfig = (array) $msgGlobalConfig + (array) $msgAppConfig;
            if (empty($msgConfig)) {
                throw new \Exception('Response msg config content empty');
            } elseif ( ! isset($msgConfig[$eCode])) {
                throw new \Exception('Response msg config content not define, ecode ' . $eCode);
            } elseif (is_array($msgConfig[$eCode]) && ! isset($msgConfig[$eCode]['emsg'])) {
                throw new \Exception('Response msg config ecode value is array but key emsg not define, ecode ' . $eCode);
            } elseif (is_array($msgConfig[$eCode]) && ! isset($msgConfig[$eCode]['desc'])) {
                throw new \Exception('Response msg config ecode value is array but key desc not define, ecode ' . $eCode);
            } else {
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
                    } else {
                        $descArr = [];
                    }
                    // 最多支持三部分定义（保证前置存在才解析下级）
                    if (isset($descArr[0]) && ! empty($descArr[0])) {
                        $tmpArr[] = $descArr[0];
                        if (isset($descArr[1]) && ! empty($descArr[1])) {
                            $tmpArr[] = $descArr[1];
                            if (isset($descArr[2]) && ! empty($descArr[2])) {
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
        if ( ! $changObject) {
            return $json;
        }
        // 支持jsonp
        $contentType = 'application/json; charset=utf-8';
        if ( ! empty($_REQUEST['jsonp'])) {
            $contentType = 'application/x-javascript; charset=utf-8';
            $json        = $_REQUEST['jsonp'] . '(' . $json . ');';
        }
        $this->setHeader('Content-Type', $contentType);
        global $responseStatus, $responseMsg;
        $responseStatus = $eCode;
        $responseMsg    = $jsonArr['meta']['emsg'];

        return $json;
    }

    /**
     * 接口错误码通用处理
     * [说明]
     * 接口中使用service方法中涉及大量的接口请求错误
     * 封装此方法减少代码冗余
     * @param  int $code
     * @return int $eCode
     */
    protected function erroneous($code)
    {
        switch ($code) {
            // curl_error
            case -1:
                $eCode = ECODE_API_NETWORK_REQUEST_FAIL;
                break;
            // http_error_code
            case -2:
                $eCode = ECODE_API_NETWORK_REQUEST_FAIL;
                break;
            // 接口网络请求失败
            case -3:
                $eCode = ECODE_API_RESPONSE_DATA_EXCEPTION;
                break;
            // 接口响应数据异常
            case -4:
                $eCode = ECODE_API_RESPONSE_DATA_EXCEPTION;
                break;
            case -6:
                $eCode = ECODE_API_PASSPORT_NOT_FOUND;
                break;
            default:
                \Lsf\Loader::plugin('Log')->error(9010513, ['code' => $code]);
                // 未知错误
                $eCode = ECODE_UNDEFINED_ERROR;
                break;
        }

        return $eCode;
    }
}
