<?php
namespace User\Service;

/**
 * 帐号服务
 * $Id: oauth.php $
 * @author mr
 */

require_once LSFPATH . '/lib/php-jwt/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class Oauth
{
    /**
     * @var mixed
     */
    //private $_svrUserModel;
    private $_svrDaoVnUserAuthModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        //$this->_svrUserModel = \Lsf\Loader::model('SvrUser', false, APP_NAME_USER);
        $this->_svrDaoVnUserAuthModel = \Lsf\Loader::model('DaoVnUserAuth', false, APP_NAME_USER);
    }

    /**
     * 验证苹果登录identityToken的合法性和有效性
     * @param void
     * @return string
     */
    public function checkAppleIdentityToken($id_token = ''){
        $data =[];
        //示例
        if (empty($id_token)) {
            return $data;
        }

        // === 下载 Apple 公钥 ===
        // Apple 公钥返回的是 JWK 格式
        $jwks = json_decode(file_get_contents('https://appleid.apple.com/auth/keys'), true);

        // === 将 JWK 转为可验证格式 ===
        $keys = JWK::parseKeySet($jwks);


        // === 4. 验证并解码 id_token ===
        try {
            $decoded = JWT::decode($id_token, $keys);
            var_dump($decoded);exit();

            if ($decoded->iss !== 'https://appleid.apple.com') {
                throw new Exception("Invalid issuer");
            }

            if ($decoded->aud !== '你的 Apple Service ID / Client ID') {
                throw new Exception("Invalid audience");
            }

            if ($decoded->exp < time()) {
                throw new Exception("expired token");
            }

            $data['apple_uid'] = $decoded->sub;
            $data['email'] = $decoded->email ?? '';


        } catch (Exception $e) {
            $errData = [
                'method'    => __FUNCTION__,
                'message' => "token verification failed",
                'ecode'     => $e->getCode(),
                'emsg'      => $e->getMessage()
            ];
            \Lsf\Loader::plugin('Log')->error(9010511, $errData);
        }
        return $data;
    }

    /**
     * 苹果登录Or注册，返回登录态
     * @param void
     * @return string
     */
    public function appleLoginOrSignUp($data){
        //根据 sub（苹果用户唯一ID）查找本地用户
        if(isset($$data['apple_uid']) && !empty($$data['apple_uid'])){
            $this->_svrDaoVnUserAuthModel->findOauthInfo($$data);
        }


//
//找不到则自动注册绑定
//
//完成本地登录流程（颁发你自己的 session / JWT）

    }
}
