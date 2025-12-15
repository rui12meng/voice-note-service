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
use Lsf\Env;
use phpDocumentor\Reflection\Types\Integer;

class Oauth
{
    /**
     * @var mixed
     */
    private $_svrDaoUserModel;
    private $_svrDaoVnUserInfoModel;
    private $_svrDaoVnUserAuthModel;
    private $_svrDaoVnUserSessionModel;
    private $_svrDaoVnUserDevicesModel;
    private $_svrDaoVnUserLogsModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        $this->_svrDaoUserModel = \Lsf\Loader::model('DaoUser', false, APP_NAME_USER);
        $this->_svrDaoVnUserInfoModel = \lsf\Loader::model('DaoVnUserInfo', false, APP_NAME_USER);
        $this->_svrDaoVnUserAuthModel = \Lsf\Loader::model('DaoVnUserAuth', false, APP_NAME_USER);
        $this->_svrDaoVnUserSessionModel = \Lsf\Loader::model('DaoVnUserSessions', false, APP_NAME_USER);
        $this->_svrDaoVnUserDevicesModel = \Lsf\Loader::model('DaoVnUserDevices', false, APP_NAME_USER);
        $this->_svrDaoVnUserLogsModel = \Lsf\Loader::model('DaoVnUserLogs', false, APP_NAME_USER);
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

        $result_data = [];

        if(!isset($data['provider']) && empty($data['provider'])){
            $data['provider'] = 'apple';
        }
        $result_data['log_mode'] = $data['provider'];
        //根据 sub（苹果用户唯一ID）查找本地用户
        if(isset($data['apple_uid']) && !empty($data['apple_uid'])){

            //查询用户是否存在
            $oauthWhereData = ['auth_type' => $data['provider'], 'identifier' =>$data['apple_uid']];
            $result = $this->_svrDaoVnUserAuthModel->findOauthInfo($oauthWhereData);
            //存在返回用户信息+登录态信息
            if(isset($result['uid'])){
                $uid = $result['uid'];

            }
            else{//找不到则自动注册绑定
                $userData = [
                    'user_uid' => 'ujrri899wuww99',//uuid_create(UUID_TYPE_RANDOM),
                    'username' => $data['username'] ?? '',
                    'email' => $data['email'] ?? '',
                    'register_type' => $data['provider'] ?? '',
                    'is_guest' => 0,
                ];

                $uid = $this->_svrDaoUserModel->storeData($userData); // 返回主键id
                if ($uid  === false ) {
                    throw new \Exception('Insert user failed');
                }

                $userInfoData = [
                    'user_id' => $uid,
                    'email' => $data['email'],
                    'nickname' => $data['username'],
                ];

                $info_id = $this->_svrDaoVnUserInfoModel->insert($userInfoData);
                if ($info_id === false) {
                    throw new \Exception('Insert user info failed');
                }

                $authData = [
                    'user_id' => $uid,
                    'auth_type' => $data['provider'] ?? '',
                    'identifier' => $data['apple_uid'] ?? '',
                    'credential' => $data['credential'] ?? '',
                    'last_login_at' => date('Y-m-d H:i:s'),
                ];
                $auth_id = $this->_svrDaoVnUserAuthModel->insert($authData);
                if ($auth_id === false) {
                    throw new \Exception('Insert auth failed');
                }
            }

            $result_data['user_id'] = $uid;
            var_dump($uid);
            //生成登录态token信息
            $result_token = $this->generateTokens($uid);

            if(!isset($result_token['access_token']) || !isset($result_token['refresh_token']) || !isset($result_token['expires_at'])){
                return false;
            }
            else{
                $result_data['token'] = $result_token['access_token'];
                $result_data['refresh_token'] = $result_token['refresh_token'];
                $result_data['expires_in'] = $result_token['expires_at'];
            }

            //存储用户会话信息
            $device_info = [
                //设备信息
                'device_id'     => $data['device_id'] ?? '',
                'device_type'     => $data['device_type'] ?? '',
                'device_name'     => $data['device_name'] ?? '',
                'device_info'     => $data['device_info'] ?? '',
                'user_agent'     => $data['user_agent'] ?? '',
                'os_version' => '15.01.89',
                'app_version' => '0.1',
                'push_token' => 'dfsdfjieuewww983j',
                'ip_address' => '127.0.0.1',
            ];

            $session_id = $this->storeUserSessionInfo($uid, $result_token['access_token'], $result_token['refresh_token'], $device_info);

            if(isset($session_id) && is_int($session_id)){

            }else{//session 信息存储失败
                return false;
            }

            // 存储用户设备信息,
            $this->storeUserDevicesInfo($uid, $device_info['device_id'], $device_info);

            //记录登录日志
            $this->storeUserLogsInfo($uid,'appleLoginOrSignUp','oauth/loginWithApple','user login', $device_info);

            //查询用户信息返回给客户端
            $userInfo = $this->_svrDaoVnUserInfoModel->findUserInfo('nickname,email,avatar_url,gender',$uid);

            $result_data['nickname'] = $userInfo[0]['nickname'] ?? '';
            $result_data['email'] = $userInfo[0]['email'] ?? '';
            $result_data['avatar_url'] = $userInfo[0]['avatar_url'] ?? '';
            $result_data['gender'] = $userInfo[0]['gender'] ?? '';
        }else{
            //验证客户端授权信息失败
            return false;

        }

        return $result_data;

    }

    /**
     * 存储用户session会话信息
     * @param int $uid
     * @param string $access_token
     * @param string $refresh_token
     * @param array $device_info
     * @return string
     */
    public function storeUserSessionInfo($uid, $access_token, $refresh_token, $device_info){
        $session_data = [
            'user_id'       => $uid,
            'refresh_token' => $refresh_token,
            'session_token'  => $access_token,
            'expire_at'    => date('Y-m-d H:i:s', time() + 3600),
            'refresh_expires_at'=> date('Y-m-d H:i:s', time() + 30*24*3600),
            'ip_address'            => $_SERVER['REMOTE_ADDR'] ?? '',
            //设备信息
            'device_id'     => $device_info['device_id'] ?? '',
            'device_type'     => $device_info['device_type'] ?? '',
            'device_name'     => $device_info['device_name'] ?? '',
            'device_info'     => json_encode($device_info ?: []), //$device_info['device_info'] ?? '',
            'user_agent'     => $device_info['user_agent'] ?? '',
            'last_active_at'    => date('Y-m-d H:i:s')
        ];
        $session_id = $this->_svrDaoVnUserSessionModel->storeData($session_data);
        if($session_id == FALSE){

            //记录log
            return false;

        }else{
            return $session_id;
        }
    }

    /**
     * 存储用户操作日志信息
     * @param int $uid
     * @param string $log_type
     * @param string $action
     * @param string $description
     * @param array $device_info
     * @return string
     */
    public function storeUserLogsInfo($uid, $log_type, $action, $description,$device_info){
        $data = [
            'user_id' => $uid,
            'log_type' => $log_type,
            'action' => $action,
            'description' => $description,
            'ip_address' => $device_info['ip_address'],
            'user_agent' => $device_info['user_agent'],
            'device_id' => $device_info['device_id'],
        ];
        $result = $this->_svrDaoVnUserLogsModel->storeLogs($data);
        if($result == FALSE){

            //记录log

        }
    }

    /**
     * 存储用户设备信息
     * @param int $uid
     * @param string $device_id
     * @param array $data
     * @return string
     */
    public function storeUserDevicesInfo($uid, $device_id, $data){
        $uid = 6;
        $device_id = 'device_idjdjkjdjsldslslsssl_djfsdjf837473';
        $data = [
            'device_type' => isset($data['device_type']) ?? 'ios',
            'device_name' => isset($data['device_type']) ?? 'mr iphone 15S',
            'os_version' => isset($data['device_type']) ?? '15.01.89S',
            'app_version' => isset($data['device_type']) ?? '0.1',
            'push_token' => isset($data['push_token']) ?? 'dfsdfjieuewww983j',
            'ip_address' => isset($data['ip_address']) ?? '127.0.0.1',
        ];
        $result = $this->_svrDaoVnUserDevicesModel->storeDevices($uid,$device_id,$data);
        // 返回布尔型，true 为成功，false为失败
        return $result;
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
            'exp' => $now + Env::get('TOKEN_ACCESS_TTL'),
        ];
        $token_jwt_access_secret = Env::get('TOKEN_JWT_ACCESS_SECRET');
        $accessToken = JWT::encode($accessPayload, $token_jwt_access_secret, 'HS256');

        // 2. refresh_token
        $refreshPayload = [
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + Env::get('TOKEN_REFRESH_TTL'),
        ];
        $refreshToken = JWT::encode($refreshPayload, Env::get('TOKEN_JWT_REFRESH_SECRET'), 'HS256');

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => $now + Env::get('TOKEN_ACCESS_TTL')
        ];
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
            var_dump($e->getMessage());
            return false;
        }
    }

    /**
     * 使用 refresh_token 刷新 access_token
     * @param $refreshToken string
     * @return void
     */
    public function refreshAccessToken(string $refreshToken){
        //1. 验证 refresh_token 是否为合法
        $payload = $this->verifyRefreshToken($refreshToken);
        if(!$payload) return false;

        $userId = $payload['sub'];

        //2. 去数据库查 user_sessions 是否注销

        //3. 颁发新的 access_token & refresh_token
        return $this->generateTokens($userId);

        //4. UPDATE 一条记录user_sessions
    }

    /**
     * 登出接口使seesion信息失效
     * @param int $uid
     * @param string $device_id
     * @return void
     */
    public function revokedSession(int $uid, string $device_id){
        $data = [
            'revoked' => 1,
            'revoked_at' => date('Y-m-d H:i:s'),
        ];
        $where = [
            'user_id' => $uid,
            'device_id' => $device_id,
        ];
        $result = $this->_svrDaoVnUserSessionModel->updateSession($data, $where);
        return $result;
    }

    /**
     * 根据uid编辑用户信息
     * @param int $uid
     * @param array $user_info
     * @return void
     */
    public function editUserInfo($uid, $user_info){
        $result = $this->_svrDaoVnUserInfoModel->editUserInfo($uid, $user_info);
        return $result;
    }

    /**
     * 根据uid查询用户信息
     * @param int $uid
     * @return void
     */
    public function getUserInfo($uid){
        // 要查询的字段
        $col = 'nickname, gender, avatar_url, timezone, language';
        $result = $this->_svrDaoVnUserInfoModel->findUserInfo($col, $uid);
        return $result;
    }

    /**
     * 用户注销
     * @param int $uid
     * @param array $params
     * @return void
     */
    public function cancellation($uid , $params){

        try{
            //1. 登录态信息失效（所有该用户的登录态）
            $data = [
                'is_deleted' => 1, //注销
            ];
            $where = [
                'user_id' => $uid,
            ];
            $result_session = $this->_svrDaoVnUserSessionModel->updateSession($data , $where);

            //2. 用户第三方绑定信息失效
            $result_auth = $this->_svrDaoVnUserAuthModel->updateOauth($data , $where);

            //3. 用户扩展资料信息失效
            $result_info = $this->_svrDaoVnUserInfoModel->updateUserInfo($data , $where);

            //4. 主表信息失效
            $where = [
                'uid' => $uid,
            ];
            $result_user = $this->_svrDaoUserModel->deleteUser($data , $where);

            //5. 日志记录(即使失败无需报错，日记记录，方便追踪)
            $data= [
                'user_id' => $uid,
                'device_id' => $params['device_id'],
                'log_type' => 'cancellation',
                'action' => '/cancellation',
                'description' => 'user cancellation',
                'ip_address' => $params['ip_address'],
                'user_agent' => $params['user_agent'],
            ];
            $result_log = $this->_svrDaoVnUserLogsModel->storeLogs($data);

            if($result_session && $result_auth && $result_info && $result_user && $result_log){
                return true;
            }else{
                return false;
            }

        }catch (\Exception $e){
            \Lsf\Loader::plugin('Log')->error(9018508, ['error' => $e, 'uid' => $uid, 'params' => $params]);
            return false;
        }

    }
}
