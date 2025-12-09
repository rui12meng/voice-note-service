<?php
namespace User\Controller;

/**
 * 用户控制器
 * $Id: member.php $
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
    public function loginOrSignup(){
        $params = $this->post('', true);
        if ( ! isset($params['login_mode']) || empty($params['login_mode'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'login_mode');
        }

        switch ($params['login_mode']) {
            case "apple":
                //apple登录
                $result = $this->loginWithApple($params);
                break;
            case "google":
                $result = $this->loginWithGoogle($params);
                break;
            default:
                //guest登录
                $result = $this->loginWithGuest($params);
                break;
        }
        return $this->json($result);
    }

    /**
     * 用户苹果授权登录
     * @param  void
     * @return string
     */
    public function loginWithApple($params){

        $user_info = [];
        //第三方授权登录必传
        if ( !isset($params['id_token']) || empty($params['id_token'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'id_token');
        }
        //我们使用自己的用户体系，所以不需要换取苹果token，且后续不会再与苹果服务交互，我们只做不为空简单校验即可
        if ( !isset($params['auth_code']) || empty($params['auth_code'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'auth_code');
        }

        if( isset($params['email']) & !empty($params['email'])){
            // 正则验证邮箱
//            if($this->validate_email($params['email'])){
//                $user_info['email'] = $params['email'];
//            }
        }

        //校验identityToken合法性且未过期
        //$data = $this->_oauthService->checkAppleIdentityToken($params['id_token']);

        //临时测试
        $data['apple_uid'] = 'sdfsdferuiweurwoeiirwoekrwop';
        if(isset($data['apple_uid']) && !empty($data['apple_uid'])){ //说明授权成功
            $user_info=[
                'apple_uid'=> $data['apple_uid'],
                'identifier' => $params['id_token'],
                'credential' => $params['auth_code'],
                'username' => $params['user_name'],
                'email' => $data['email'],
                'provider' => 'apple',
            ];
            //登录or注册逻辑
            $this->_oauthService->appleLoginOrSignUp($user_info);
        }




        if( isset($params['user_name']) & !empty($params['user_name'])){
            $user_info['user_name'] = $params['user_name'];
        }


        $result = [];
        return $this->json($result);
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
        return $this->json($result);
    }

    /**
     * 用户游客登录
     * @param  void
     * @return string
     */
    public function loginWithGuest($params){

        $result = [];

        return $this->json($result);
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
