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
use Firebase\JWT\Key;
use \Lsf\Env;

class Oauth
{
    /**
     * @var mixed
     */
    private $_svrDaoUserModel;
    private $_svrDaoVnUserAuthModel;
    private $_svrDaoVnUserSessionModel;
    private $_svrDaoVnUserDevicesModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        $this->_svrDaoUserModel = \Lsf\Loader::model('DaoUser', false, APP_NAME_USER);
        $this->_svrDaoVnUserAuthModel = \Lsf\Loader::model('DaoVnUserAuth', false, APP_NAME_USER);
        $this->_svrDaoVnUserSessionModel = \Lsf\Loader::model('DaoVnUserSessions', false, APP_NAME_USER);
        $this->_svrDaoVnUserDevicesModel = \Lsf\Loader::model('DaoVnUserDevicess', false, APP_NAME_USER);
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

            if ($decoded->iss !== 'https://appleid.apple.com') {
                throw new \Exception("Invalid issuer");
            }

            if ($decoded->aud !== '你的 Apple Service ID / Client ID') {
                throw new \Exception("Invalid audience");
            }

            if ($decoded->exp < time()) {
                throw new \Exception("expired token");
            }

            $data['apple_uid'] = $decoded->sub;
            $data['email'] = $decoded->email ?? '';


        } catch (\Exception $e) {
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
        //test
        $uid = 6;
        $device_id = 'device_idjdjkjdjsldslslsssl_djfsdjf837473';
        $data = [
            'device_type' => 'ios',
            'device_name' => 'mr iphone 15S',
            'os_version' => '15.01.89',
            'app_version' => '0.1',
            'push_token' => 'dfsdfjieuewww983j',
            'ip_address' => '127.0.0.1',
        ];
        $result = $this->_svrDaoVnUserDevicesModel->storeDevices($uid,$device_id,$data);
        var_dump($result);
        exit();

        if(!isset($data['provider']) && empty($data['provider'])){
            $data['provider'] = 'apple';
        }
        //根据 sub（苹果用户唯一ID）查找本地用户
        if(isset($data['apple_uid']) && !empty($data['apple_uid'])){
            $oauthWhereData = ['auth_type' => $data['provider'], 'identifier' =>$data['apple_uid']];
            $result = $this->_svrDaoVnUserAuthModel->findOauthInfo($oauthWhereData);
            //找到则返回用户uid
            if(isset($result['uid'])){
                $uid = $result['uid'];
            }else{//找不到则自动注册绑定
                //事务处理
                $result = $this->_svrDaoUserModel->userSign($data);

                if(isset($result) && is_int($result) && $result > 0){
                    $uid = $result;
                    //同时把用户输入信息部分返回
                    //生成登录态token信息
                    $result_token = generateTokens($uid);
                    //存储用户会话信息
                    $session_data = [
                        'user_id'       => $result['uid'],
                        'auth_id'       => $result['auth_id'],
                        'refresh_token' => $result_token['refresh_token'],
                        'session_token'  => $result_token['access_token'],
                        'expire_at'    => date('Y-m-d H:i:s', time() + 3600),
                        'refresh_expires_at'=> date('Y-m-d H:i:s', time() + 30*24*3600),
                        'ip_address'            => $_SERVER['REMOTE_ADDR'] ?? '',
                        //设备信息
                        'device_id'     => $data['device_id'] ?? '',
                        'device_type'     => $data['device_type'] ?? '',
                        'device_name'     => $data['device_name'] ?? '',
                        'device_info'     => $data['device_info'] ?? '',
                        'user_agent'     => $data['user_agent'] ?? '',
                        'last_active_at'    => date('Y-m-d H:i:s')
                    ];
                    $session_id = $this->_svrDaoVnUserSessionModel->storeData($session_data);

                    if(isset($session_id) && is_int($session_id)){

                    }else{//session 信息存储失败
                        return false;
                    }


                }else{
                    return 'err';
                    //log
                }
            }

        }else{
            //验证客户端授权信息失败

        }
return [];

    }

    /**
     * 存储用户session会话信息
     * @param void
     * @return string
     */
    public function storeUserSessionInfo($data){

    }

    /**
     * 存储用户日志信息
     * @param void
     * @return string
     */
    public function storeUserLogsInfo($data){

    }

    /**
     * 存储用户设备信息
     * @param void
     * @return string
     */
    public function storeUserDevicesInfo($data){

    }

    /**
     * 生成 JWT token
     * @param void
     * @return string
     */
    public function generateTokens(int $userId) : array {
        $now = time();

        // 1. access_token
        $accessPayload = [
            'sub' => $userId,        // 用户ID
            'iat' => $now,           // 签发时间
            'exp' => $now + \Lsf\Env::get('TOKEN_ACCESS_TTL'),
        ];
        $accessToken = JWT::encode($accessPayload, \Lsf\Env::get('TOKEN_JWT_ACCESS_SECRET'), 'HS256');

        // 2. refresh_token
        $refreshPayload = [
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + \Lsf\Env::get('TOKEN_REFRESH_TTL'),
        ];
        $refreshToken = JWT::encode($refreshPayload, \Lsf\Env::get('TOKEN_JWT_REFRESH_SECRET'), 'HS256');

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => $now + \Lsf\Env::get('TOKEN_ACCESS_TTL')
        ];
    }

    /**
     * 验证 access_token
     * @param void
     * @return void
     */
    public function verifyAccessToken(string $token) {
        try{
            $payload = JWT::decode($token, new Key(\Lsf\Env::get('TOKEN_JWT_ACCESS_SECRET'), 'HS256'));
            return (array)$payload;
        }catch (\Exception $e) {
            //log access解析失败，非法token
            return false;
        }
    }

    /**
     * 验证 refresh_token
     * @param void
     * @return void
     */
    public function verifyRefreshToken(string $token) {
        try {
            $payload = JWT::decode($token,  new Key(\Lsf\Env::get('TOKEN_JWT_REFRESH_SECRET'), 'HS256'));
            return (array)$payload;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 使用 refresh_token 刷新 access_token
     * @param $refreshToken string
     * @return void
     */
    public function refreshAccessToken(string $refreshToken){
        $payload = $this->verifyRefreshToken($refreshToken);
        if(!$payload) return false;

        $userId = $payload['sub'];
        return $this->generateTokens($userId);
    }
}
