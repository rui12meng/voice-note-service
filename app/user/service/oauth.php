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
use Lsf\Env;

class Oauth extends \Service\Base
{
    /**
     * @var mixed
     */
    private $_svrDaoVnUserModel;
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
        $this->_svrDaoVnUserModel = \Lsf\Loader::model('DaoVnUser', true);
        $this->_svrDaoVnUserInfoModel = \lsf\Loader::model('DaoVnUserInfo', true);
        $this->_svrDaoVnUserAuthModel = \Lsf\Loader::model('DaoVnUserAuth', true);
        $this->_svrDaoVnUserSessionModel = \Lsf\Loader::model('DaoVnUserSessions', true);
        $this->_svrDaoVnUserDevicesModel = \Lsf\Loader::model('DaoVnUserDevices', true);
        $this->_svrDaoVnUserLogsModel = \Lsf\Loader::model('DaoVnUserLogs', true);
    }

    /**
     * 验证苹果登录identityToken的合法性和有效性
     * @param void
     * @return string
     */
    private function _checkAppleIdentityToken($id_token = ''){
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

            $data['sub'] = $decoded->sub;
            $data['email'] = $decoded->email ?? '';


        } catch (\Exception $e) {
            $errData = [
                'method'    => __FUNCTION__,
                'message' => "token verification failed",
                'ecode'     => $e->getCode(),
                'emsg'      => $e->getMessage()
            ];
            \Lsf\Loader::plugin('Log')->error(9010511, $errData);
            throw new \Exception("invalid id_token");
        }
        return $data;
    }

    /**
     * 苹果登录Or注册，返回登录态
     * @param void
     * @return string
     */
    public function appleLoginOrSignUp($data){
        $data['is_guest'] = 0;
        if(!isset($data['provider']) || empty($data['provider'])){
            $data['provider'] = 'apple';
        }
        if(isset($data['id_token']) && !empty($data['id_token'])){
            //校验identityToken合法性且未过期
            try{
                $decoded = $this->_checkAppleIdentityToken($data['id_token']);
                if(isset($decoded['sub'])) $data['sub'] = $decoded['sub'];
                if(isset($decoded['email'])) $data['email'] = $decoded['email'];
            }catch(\Exception $e){
                //log
                return -101;// id_token 无效
            }
        }
        if(!isset($data['sub']) || empty($data['sub'])){
            return -101; // id_token 无效
        }
        $identifier = $data['sub'];
        $action = 'appleLoginOrSignUp';
        $res = $this->_loginOrSignUpByIdentifier($data['provider'], $identifier, $data, $action);
        return $res;
    }

    /**
     * 游客登录Or注册，返回登录态
     * @param void
     * @return string
     */
    public function guestLoginOrSignUp($data){
        $data['is_guest'] = 1;
        if(!isset($data['provider']) || empty($data['provider'])){
            $data['provider'] = 'guest';
        }
        if(!isset($data['device_id']) || empty($data['device_id'])){
            return -201;
        }
        $identifier = $data['device_id'];
        $action = 'guestLoginOrSignUp';
        $res = $this->_loginOrSignUpByIdentifier($data['provider'], $identifier, $data, $action);
        return $res;
    }

    private function _loginOrSignUpByIdentifier($provider, $identifier, $data, $action){

        $result = $this->_svrDaoVnUserAuthModel->findOauthInfo($provider, $identifier);
        //todo 存在记录
        if(isset($result[0]['user_id']) && isset($result[0]['is_deleted'])){
            $uid = $result[0]['user_id'];
            $is_deleted = $result[0]['is_deleted'];
            if($is_deleted === 1){ //todo 注销用户重新注册/登录
                $newUid = $this->createUser($data, 're-registered');
                if($newUid === false){
                    return -7;
                }
                $logData = [
                    'user_id' => $newUid,
                    'device_id' => $data['device_id'] ?? '',
                    'log_type' => 'login(signed up with a new account using this provider)，old user id:'. $uid,
                    'action' => $action,
                    'description' => 'User re-registered with '.$provider.'.',
                    'ip_address' => $data['ip_address'] ?? '',
                    'user_agent' => $data['user_agent'] ?? '',
                ];
                $uid = $newUid;
            }else{ //todo 用户登录
                $logData = [
                    'user_id' => $uid,
                    'device_id' => $data['device_id'] ?? '',
                    'log_type' => 'User login',
                    'action' => $action,
                    'description' => 'User login with '.$provider.'.',
                    'ip_address' => $data['ip_address'] ?? '',
                    'user_agent' => $data['user_agent'] ?? '',
                ];
            }
        }else{ //todo 记录不存在；注册流程
            $uid = $this->createUser($data);
            if($uid === false){
                return -7;
            }
            $logData = [
                'user_id' => $uid,
                'device_id' => $data['device_id'] ?? '',
                'log_type' => 'login(signed up with a new account using this provider)',
                'action' => $action,
                'description' => 'User registered with '.$provider.'.',
                'ip_address' => $data['ip_address'] ?? '',
                'user_agent' => $data['user_agent'] ?? '',
            ];
        }
        $resultLog = $this->_svrDaoVnUserLogsModel->storeLogs($logData);
        if($resultLog === false){
            return -7;
        }
        $result_data = $this->recordSessionContext($uid, $data);
        return $result_data;
    }

    /**
     * 注册新用户（内部方法）
     * @param array $data
     * @param string $type
     * @return string
     */
    private function createUser($data , $type = 'registered'){

        $userData = [
            'uuid' => \Lsf\Uuid::v7(),
            'username' => $data['username'] ?? '',
            'email' => $data['email'] ?? '',
            'register_type' => $data['provider'] ?? '',
            'is_guest' => $data['is_guest'],
        ];

        $this->_svrDaoVnUserModel->begin();
        $uid = $this->_svrDaoVnUserModel->insert($userData);
        if($uid === false){
            $this->_svrDaoVnUserModel->rollback();
            return false;
        }

        $userInfoData = [
            'user_id' => $uid,
            'email' => $data['email'] ?? '',
            'nickname' => $data['username'] ?? '',
        ];

        $infoId = $this->_svrDaoVnUserInfoModel->insert($userInfoData);

        if($infoId === false){
            $this->_svrDaoVnUserModel->rollback();
            return false;
        }

        //todo 登录方式不同，取参不同[游客登录，唯一身份识别标志为device_id；apple/google 唯一身份标识为apple/google返回的唯一uid]
        if($data['provider'] == 'guest'){
            $identifier = $data['device_id'];
        }else{
            $identifier = $data['sub'];
        }

        $authData = [
            'user_id' => $uid,
            'auth_type' => $data['provider'] ?? '',
            'identifier' => $identifier,
            'last_login_at' => date('Y-m-d H:i:s'),
        ];
        if($type == 're-registered'){ //说明是注销用户重新注册
            //更新auth表
            $row = $this->_svrDaoVnUserAuthModel->updateOauth(['is_deleted' => 0 , 'user_id' => $uid],['auth_type' => $data['provider'] , 'identifier' => $identifier]);
            if($row === false){
                $this->_svrDaoVnUserModel->rollback();
                return false;
            }
        }else{ //新用户
            $authId = $this->_svrDaoVnUserAuthModel->insert($authData);
            if($authId === false){
                $this->_svrDaoVnUserModel->rollback();
                return false;
            }
        }
        $this->_svrDaoVnUserModel->commit();
        return $uid;
    }

    /**
     * 存储用户登录session+设备+日志（内部方法）
     * @param int $uid
     * @param array $data
     * @return string
     */
    private function recordSessionContext($uid, $data){
        //生成登录态token信息
        $result_token = $this->generateTokens($uid);

        if(!isset($result_token['access_token']) || !isset($result_token['refresh_token']) || !isset($result_token['expires_at'])){
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'call'      => 'generateTokens',
                'result'    => $result_token,
                'message'   => 'generate jwt token failed',
            ]);
            return -1; // 获取token 失败
        }else{
            $result_data['token'] = $result_token['access_token'];
            $result_data['refresh_token'] = $result_token['refresh_token'];
            $result_data['expires_in'] = $result_token['expires_at'];
        }

        $data = [
            'device_id'     => $data['device_id'] ?? '',
            'device_type'     => $data['device_type'] ?? '',
            'device_name'     => $data['device_name'] ?? '',
            'user_agent'     => $data['user_agent'] ?? '',
            'ip_address'     => $data['ip_address'] ?? '',
        ];

        $sessionId = $this->storeUserSessionInfo($uid, $result_token['jti'],$result_token['access_token'], $result_token['refresh_token'], $data);

        if ($sessionId === false) {//session 信息存储失败
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'call'      => 'mysql user session insert',
                'result'    => $sessionId,
                'message'   => 'Insert user session failed',
            ]);
            return -7;
        }

        $deviceExtra = []; //设备扩展信息
        $data = [
            'device_id'     => $data['device_id'] ?? '',
            'os_version'     => $data['os_version'] ?? '',
            'app_version'    => $data['device_type'] ?? '',
            'push_token'     => $data['device_name'] ?? '',
            'last_login_at'  => date('Y-m-d H:i:s'),
            'device_info'    => isset($deviceExtra) ? json_encode($deviceExtra, JSON_UNESCAPED_UNICODE): json_encode((object)[], JSON_UNESCAPED_UNICODE), //设备扩展信息
        ];

        // 存储用户设备信息
        $device_id = $this->storeUserDevicesInfo($uid, $data['device_id'], $data);
        if ($device_id === false) {//session 信息存储失败
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'call'      => 'mysql user device insert',
                'result'    => $device_id,
                'message'   => 'Insert user device failed',
            ]);
            return -7;
        }
        //查询用户信息返回给客户端
        $userInfo = $this->_svrDaoVnUserInfoModel->findUserInfo('nickname,email,avatar_url,gender,language',$uid);

        if($userInfo === false){
            return -7;
        }else{
            $result_data['nickname'] = $userInfo[0]['nickname'] ?? '';
            $result_data['email'] = $userInfo[0]['email'] ?? '';
            $result_data['avatar_url'] = $userInfo[0]['avatar_url'] ?? '';
            $result_data['gender'] = $userInfo[0]['gender'] ?? '';
            $result_data['language'] = $userInfo[0]['language'] ?? '';
        }

        return $result_data;

    }

    /**
     * 存储用户session会话信息
     * @param int $uid
     * @params string $jti
     * @param string $access_token
     * @param string $refresh_token
     * @param array $data
     * @return string
     */
    public function storeUserSessionInfo($uid, $jti, $access_token, $refresh_token, $data){

        $session_data = [
            'user_id'       => $uid,
            'jti'           => $jti,
            'refresh_token' => $refresh_token,
            'session_token'  => $access_token,
            'expire_at'    => date('Y-m-d H:i:s', time() + \Lsf\Env::get('TOKEN_ACCESS_TTL')),
            'refresh_expires_at'=> date('Y-m-d H:i:s', time() + \Lsf\Env::get('TOKEN_REFRESH_TTL')),
            'ip_address'            => $data['ip_address'] ?? '',
            //设备信息
            'device_id'     => $data['device_id'] ?? '',
            'device_type'     => $data['device_type'] ?? '',
            'device_name'     => $data['device_name'] ?? '',
            'user_agent'     => $data['user_agent'] ?? '',
            'last_active_at'    => date('Y-m-d H:i:s')
        ];
        $session_id = $this->_svrDaoVnUserSessionModel->insert($session_data);
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
        return $result;
    }

    /**
     * 存储用户设备信息
     * @param int $uid
     * @param string $deviceId
     * @param array $data
     * @return string
     */
    public function storeUserDevicesInfo($uid, $deviceId, $data){
        //uid+deviceId 为唯一索引，无则新增；有则更新
        $result = $this->_svrDaoVnUserDevicesModel->storeDevices($uid,$deviceId,$data);
        // 返回布尔型，true 为成功，false为失败
        return $result;
    }



}
