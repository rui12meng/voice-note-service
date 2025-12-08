<?php
namespace App;

use Lsf\Exception\FinishException;

/**
 * 应用全局基类
 * $Id: application.php $
 * @author mr
 */

define('ECODE_SUCCESS', 0);                              // 成功
define('ECODE_PARAM_MISSING', 9040000);                  // 缺失参数
define('ECODE_PARAM_VALUE_INVALID', 9040001);            // 参数值非法
define('ECODE_DATABASE_QUERY_FAIL', 9040002);            // 数据库查询失败
define('ECODE_DATA_NOT_FOUND', 9040004);                 // 数据不存在
define('ECODE_API_NETWORK_REQUEST_FAIL', 9040005);       // 接口网络请求失败
define('ECODE_API_RESPONSE_DATA_EXCEPTION', 9040006);    // 接口响应数据异常
define('ECODE_CALL_INNER_METHOD_PARAMS_ERROR', 9040007); // 调用内部方法参数错误
define('ECODE_SEND_SMS_TOO_OFTEN', 9040008);             // 发送短信过于频繁
define('ECODE_SEND_SMS_FAIL', 9040009);                  // 发送短信失败
define('ECODE_UNDEFINED_ERROR', 9040010);                // 未知错误
define('ECODE_API_RESPONSE_CODE_ERROR', 9040011);        // 接口响应错误
define('ECODE_UPOLOAD_ERROR', 9040013);                  // 魔拍图片上传失败
define('ECODE_SEND_REQUEST_TOO_OFTEN', 9040014);         // 请求频率过于频繁
define('ECODE_PIC_YELLOW', 9040016);                     // 图片鉴定为黄色

define('ECODE_INVALID_QRCODE', 9040100); // 请扫描有效二维码

define('ECODE_SMS_CHECK_FAIL', 9040201);           // 验证失败
define('ECODE_SMS_CHECK_TIMEOUT', 9040202);        // 验证超时
define('ECODE_SMS_CHECK_EXCEPTION', 9040203);      // 验证异常
define('ECODE_SMS_TYPE_UNDEFINED', 9040204);       // 场景不存在
define('ECODE_SMS_PHONE_ERROR', 9040205);          // 手机号码有误
define('ECODE_CHECK_CODE_ERROR', 9040206);         // 手机号码有误
define('ECODE_OCR_SUBJECT_ERROR', 9040207);        // 未能识别到学科
define('ECODE_OCR_SUBJECT_INCONFORMITY', 9040208); // 识别的学科与传入的学科不一致
define('ECODE_OCR_MARKS_ERROR', 9040209);          // 未能识别到错误标识
define('ECODE_CREATE_CODE_ERROR', 9040210);        // 生成验证码失败
define('ECODE_OCR_QUESTION_ERROR', 9040211);       // 未能识别到切题标识

class Application extends \Lsf\Controller
{
    const SERVER_SWITCH_CODE = 10002;

    /**
     * @var int
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
        // 平台
        '/platform/app_config/new_protocol'     => 1,
        '/platform/app_config/android_download' => 1,
        '/platform/app_config/app_upgrade'      => 1,
        // 帐号
        '/passport/user/login'                  => 1,
        '/passport/user/key'                    => 1,
        '/passport/user/send_code'              => 1,
        '/passport/user/check_code'             => 1,
        '/passport/user/change_password'        => 1,
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
        // $this->_systemMaintenance();
        // 先执行全局的初始化方法
        $this->uid   = $this->post('uid', true);
        $this->token = $this->post('token', true);
        // 接口限流器
        $this->_apiCurrentLimiter($this->uid);
        // $this->_init();
        // 再执行每个app自定义的初始化方法
        $this->initAppsApplication();
    }

    /**
     * 全局初始化
     * @param  void
     * @return void
     */
    private function _init()
    {
        $router = '/' . $this->appName . '/' . $this->controllerName . '/' . uncamelize($this->actionName);
        // 登录token验证
        if (array_key_exists($router, $this->noNeedCheckTokenRouter) === false) {
            // 检查token是否有效
            $this->uid   = $this->post('uid', true);
            $this->token = $this->post('token', true);
            if ( ! isset($this->uid) || empty($this->uid)) {
                throw new FinishException($this->errParamMissing(ECODE_PARAM_MISSING, 'uid'));
            }
            if ( ! is_numeric($this->uid) || intval($this->uid) <= 0) {
                throw new FinishException($this->errParamMissing(ECODE_PARAM_VALUE_INVALID, 'uid'));
            }
            if ( ! isset($this->token) || empty($this->token)) {
                throw new FinishException($this->errParamMissing(ECODE_PARAM_MISSING, 'token'));
            }
            if ($this->token == '') {
                throw new FinishException($this->errParamMissing(ECODE_PARAM_VALUE_INVALID, 'token'));
            }
            $checkResult = ['code' => 0];
            // 需要对token失效和账户被顶做处理
            if ($checkResult['code'] != 0) {
                switch ($checkResult['code']) {
                    // token失效
                    case -7:
                        $eCode = 9040003;
                        break;
                    // token获取用户信息失败
                    case -105:
                    case -109:
                        $eCode = 9040006;
                        break;
                    default:
                        $eCode = 9040003;
                        break;
                }
                throw new FinishException($this->json($eCode));
            }
        }
    }

    /**
     * 接口限流器
     * @param  int    $uid
     * @return void
     */
    private function _apiCurrentLimiter($uid)
    {
        //$config = \Lsf\Loader::plugin('ConfigCenter')->group('api_current_limter');
        //$config =\Lsf\Env::group('REDIS_');
        $config =[];
        if (isset($config['uris']) && ! empty($config['uris'])) {
            $router = '/' . $this->appName . '/' . $this->controllerName . '/' . uncamelize($this->actionName);
            $uriArr = json_decode($config['uris'], true);
            if (isset($uriArr[$router])) {
                $ratio = (int) $uriArr[$router];
                if ($ratio <= 0) {
                    $result = false;
                } elseif ($ratio >= 100) {
                    $result = true;
                } else {
                    $uid    = (int) $uid;
                    $num    = $uid > 0 ? $uid : mt_rand(81000000000, 81999999999);
                    $value  = ($num % 100) + 1;
                    $result = $value > $ratio ? false : true;
                }
                if ($result === false) {
                    throw new FinishException($this->json(9999998));
                }
            }
        }
    }

    /**
     * 系统维护
     * @param  void
     * @return void
     */
    private function _systemMaintenance()
    {
        $redisKey  = 'ok-student-middle:system_maintenance';
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
    public function errParamValueInvalid($eCode, $paramName, $value)
    {
        return $this->json($eCode, ['param_name' => $paramName, 'value' => $value]);
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
            default:
                \Lsf\Loader::plugin('Log')->error(9040513, ['code' => $code]);
                // 未知错误
                $eCode = ECODE_UNDEFINED_ERROR;
                break;
        }

        return $eCode;
    }
}
