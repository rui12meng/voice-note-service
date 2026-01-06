<?php
namespace User\Controller;

/**
 * 用户控制器
 * $Id: oauth.php $
 * @author mengrui
 */
class Oauth extends \App\Application
{

    /**
     * @var mixed
     */
    private $_oauthService;

    /**
     * 构造函数
     * @param  string $appName
     * @param  string $controllerName
     * @param  string $actionName
     * @return void
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);
        $this->_oauthService = \Lsf\Loader::service('Oauth', false, APP_NAME_USER);
    }

    /**
     * 用户登录统一入口（若未注册，则登录即注册）
     * @param  void
     * @return string
     */
    public function loginOrSignUp(){
        $result = [];
        $params = $this->post('', true);
        if ( ! isset($params['login_mode']) || empty($params['login_mode'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'login_mode');
        }

        switch ($params['login_mode']) {
            case "apple":
                //apple登录
                $response = $this->loginWithApple($params);
                break;
            case "google":
                $response = $this->loginWithGoogle($params);
                break;
            default:
                //guest登录
                $response = $this->loginWithGuest($params);
                break;
        }

        $result = [];
        $eCode = ECODE_SUCCESS;
        if (is_int($response) && $response < 0) {
            switch ($response) {
                case -101: // Apple - id_token无效
                    $eCode = 1008013;
                    break;
                case -201: // guest登录参数异常
                    $eCode = 1008016;
                    break;
                case -7: // 数据库异常
                    $eCode = 1008017;
                    break;
                case -1: //获取token 失败
                    $eCode = 1008018;
                    break;
                default: // 未知错误
                    $eCode = 1008019;
                    break;
            }
        } else {
            $result = $response;
        }
        return $this->json($eCode, $result);
    }

    /**
     * 用户苹果授权登录
     * @param  void
     * @return string
     */
    public function loginWithApple($params){

        $user_info = [];
        //apple授权登录必传
        if ( !isset($params['id_token']) || empty($params['id_token'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'id_token');
        }
        //我们使用自己的用户体系，所以不需要换取苹果token，且后续不会再与苹果服务交互，我们只做不为空简单校验即可
        if ( !isset($params['auth_code']) || empty($params['auth_code'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'auth_code');
        }

        if( isset($params['email']) & !empty($params['email'])){
            // 正则验证邮箱
            if($this->validateEmail($params['email'])){
                $user_info['email'] = $params['email'];
            }
        }
        //登录or注册逻辑
        $result = $this->_oauthService->appleLoginOrSignUp($params);

        return $result;
    }

    /**
     * 用户谷歌授权登录
     * @param  void
     * @return string
     */
    public function loginWithGoogle($params){

        //第三方授权登录必传
        if ( ! isset($params['id_token']) || empty($params['id_token'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'id_token');
        }
        if ( ! isset($params['auth_code']) || empty($params['auth_code'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'auth_code');
        }

        $result = [];
        return $this->json(ECODE_SUCCESS, $result);
    }

    /**
     * 用户游客登录
     * @param  void
     * @return string
     */
    public function loginWithGuest($params){

        if ( !isset($params['device_id']) || empty($params['device_id'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'device_id');
        }
        //登录or注册逻辑
        $result = $this->_oauthService->guestLoginOrSignUp($params);

        return $result;
    }

    /**
     * 正则验证邮箱格式[较宽松验证，非精准匹配]
     * @param  void
     * @return string
     */
    public function validateEmail($email = '')
    {
        $email = trim(strtolower($email));

        // 1. 邮箱不能为空
        if ($email === '') {
            return false;
        }

        // 2. 基础长度限制（防攻击）
        if (strlen($email) > 128) {
            return false;
        }

        // 3. 通用邮箱正则（支持 Apple 隐藏邮箱）
        $regex = '/^[A-Za-z0-9._%+-]+@([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$/';

        if (!preg_match($regex, $email)) {//邮箱格式不正确
            return false;
        }

        return true;
    }



}
