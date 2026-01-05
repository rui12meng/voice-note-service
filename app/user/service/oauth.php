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

        $result_data = [];

        if(!isset($data['provider']) && empty($data['provider'])){
            $data['provider'] = 'apple';
        }
        $result_data['log_mode'] = $data['provider'];
        //根据 sub（苹果用户唯一ID）查找本地用户
        if(isset($data['apple_uid']) && !empty($data['apple_uid'])){

            //根据auth_type+sub唯一索引查询用户是否存在
            $auth_type = $data['provider'];
            $auth_sub = $data['apple_uid'];
            $result = $this->_svrDaoVnUserAuthModel->findOauthInfo($auth_type, $auth_sub);

            //无记录	→ 创建新用户 + 新 OAuth 绑定
            //有记录且 is_deleted = 0	→ 正常登录（该用户已存在）
            //有记录且 is_deleted = 1	→ 删除该记录（或更新为无效），然后走“无记录”流程
            if(isset($result[0]['user_id']) && isset($result[0]['is_deleted'])){
                $uid = $result[0]['user_id'];
                $is_deleted = $result[0]['is_deleted'];
                if($is_deleted === 1){ //注销用户，重新走注册流程【新建uid，重新绑定oauth】
                    $new_uid = $this->createUser($data);
                    //更新auth表
                    $this->_svrDaoVnUserAuthModel->updateOauth(['is_deleted' => 0 , 'user_id' => $new_uid],['auth_type' => $auth_type , 'identifier' => $auth_sub]);
                    //记录log
                    $logData = [
                        'user_id' => $new_uid,
                        'device_id' => $data['device_id'],
                        'log_type' => 'login(signed up with a new account using this provider)',
                        'action' => 'appleLoginOrSignUp',
                        'description' => 'User re-registered with OAuth (oauth_type=apple). Previous binding was soft-deleted; new user ID created. old user ID is:'.$uid,
                        'ip_address' => $data['ip_address'],
                        'user_agent' => $data['user_agent'],
                    ];
                    $result = $this->_svrDaoVnUserLogsModel->storeLogs($logData);
                    if($result === false){
                        return false;
                    }
                    $uid = $new_uid;
                }

            }else{//找不到则自动注册绑定
                $uid = $this->createUser($data);
                $authData = [
                    'user_id' => $uid,
                    'auth_type' => $data['provider'] ?? '',
                    'identifier' => $data['apple_uid'] ?? '',
                    'credential' => $data['credential'] ?? '',
                    'last_login_at' => date('Y-m-d H:i:s'),
                ];
                $auth_id = $this->_svrDaoVnUserAuthModel->insert($authData);
                if ($auth_id === false) {
                    \Lsf\Loader::plugin('Log')->error(9040511, [
                        'call'      => 'mysql user auth insert',
                        'result'    => $auth_id,
                        'message'   => 'Insert user auth failed',
                    ]);
                    return false;
                }
            }

            $result_data = $this->recordSessionContext($uid, $data);

        }else{
            //验证客户端授权信息失败
            return false;
        }

        return $result_data;

    }

    /**
     * 游客登录Or注册，返回登录态
     * @param void
     * @return string
     */
    public function guestLoginOrSignUp($data){
        $result_data = [];

        if(!isset($data['provider']) && empty($data['provider'])){
            $data['provider'] = 'guest';
        }
        $result_data['log_mode'] = $data['provider'];

        $result = $this->_svrDaoVnUserAuthModel->findOauthInfo($data['provider'], $data['device_id']);

        if(isset($result[0]['user_id']) && isset($result[0]['is_deleted'])){
            $uid = $result[0]['user_id'];
            $is_deleted = $result[0]['is_deleted'];
            if($is_deleted === 1){ // 注销用户
                $uid = $this->createUser($data, 're-registered');
                if(is_int($uid) && $uid < 0){
                    return $uid;
                }
                //记录log
                $logData = [
                    'user_id' => $uid,
                    'device_id' => $data['device_id'],
                    'log_type' => 'login(signed up with a new account using this provider)',
                    'action' => 'guestLoginOrSignUp',
                    'description' => 'User re-registered with Guest (oauth_type=guest). Previous binding was soft-deleted; new user ID created. old user ID is:'.$uid,
                    'ip_address' => $data['ip_address'],
                    'user_agent' => $data['user_agent'],
                ];
            }else{ //登录
                //记录log
                $logData = [
                    'user_id' => $uid,
                    'device_id' => $data['device_id'],
                    'log_type' => 'User login',
                    'action' => 'guestLoginOrSignUp',
                    'description' => 'User login with Guest (oauth_type=guest). ',
                    'ip_address' => $data['ip_address'],
                    'user_agent' => $data['user_agent'],
                ];
            }

        }else{//找不到则自动注册绑定
            $uid = $this->createUser($data);
            if(is_int($uid) && $uid < 0){
                return $uid;
            }
            $logData = [
                'user_id' => $uid,
                'device_id' => $data['device_id'],
                'log_type' => 'login(signed up with a new account using this provider)',
                'action' => 'guestLoginOrSignUp',
                'description' => 'User registered with Guest (oauth_type=guest).',
                'ip_address' => $data['ip_address'],
                'user_agent' => $data['user_agent'],
            ];
        }
        //记录log
        $result = $this->_svrDaoVnUserLogsModel->storeLogs($logData);
        if($result === false){
            return false;
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
            'uuid' => '',
            'username' => $data['username'] ?? '',
            'email' => $data['email'] ?? '',
            'register_type' => $data['provider'] ?? '',
            'is_guest' => 0,
        ];

        $this->_svrDaoVnUserModel->begin();
        $uid = $this->_svrDaoVnUserModel->insert($userData);
        if($uid === false){
            $this->_svrDaoVnUserModel->rollback();
            return -7;
        }

        $userInfoData = [
            'user_id' => $uid,
            'email' => isset($data['email']) ?? '',
            'nickname' => isset($data['username']) ?? '',
        ];

        $infoId = $this->_svrDaoVnUserInfoModel->insert($userInfoData);

        if($infoId === false){
            $this->_svrDaoVnUserModel->rollback();
            return -7;
        }

        $authData = [
            'user_id' => $uid,
            'auth_type' => $data['provider'] ?? '',
            'identifier' => $data['device_id'] ?? '',
            'last_login_at' => date('Y-m-d H:i:s'),
        ];
        if($type == 're-registered'){ //说明是注销用户重新注册
            //更新auth表
            $row = $this->_svrDaoVnUserAuthModel->updateOauth(['is_deleted' => 0 , 'user_id' => $uid],['auth_type' => $data['provider'] , 'identifier' => $data['device_id']]);
            if($row === false){
                $this->_svrDaoVnUserModel->rollback();
                return -7;
            }
        }else{ //新用户
            $authId = $this->_svrDaoVnUserAuthModel->insert($authData);
            if($authId === false){
                $this->_svrDaoVnUserModel->rollback();
                return -7;
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
            return false;
        }else{
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

        $session_id = $this->storeUserSessionInfo($uid, $result_token['jti'],$result_token['access_token'], $result_token['refresh_token'], $device_info);

        if ($session_id === false) {//session 信息存储失败
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'call'      => 'mysql user session insert',
                'result'    => $session_id,
                'message'   => 'Insert user session failed',
            ]);
            return false;
        }
        // 存储用户设备信息,
        $device_id = $this->storeUserDevicesInfo($uid, $device_info['device_id'], $device_info);
        if ($device_id === false) {//session 信息存储失败
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'call'      => 'mysql user device insert',
                'result'    => $device_id,
                'message'   => 'Insert user device failed',
            ]);
        }

        //记录登录日志
        $this->storeUserLogsInfo($uid,'appleLoginOrSignUp','oauth/loginWithApple','user login', $device_info);

        //查询用户信息返回给客户端
        $userInfo = $this->_svrDaoVnUserInfoModel->findUserInfo('nickname,email,avatar_url,gender',$uid);

        if($userInfo === false){
            return false;
        }else{
            $result_data['nickname'] = $userInfo[0]['nickname'] ?? '';
            $result_data['email'] = $userInfo[0]['email'] ?? '';
            $result_data['avatar_url'] = $userInfo[0]['avatar_url'] ?? '';
            $result_data['gender'] = $userInfo[0]['gender'] ?? '';
        }

        return $result_data;

    }

    /**
     * 存储用户session会话信息
     * @param int $uid
     * @params string $jti
     * @param string $access_token
     * @param string $refresh_token
     * @param array $device_info
     * @return string
     */
    public function storeUserSessionInfo($uid, $jti, $access_token, $refresh_token, $device_info){
        $session_data = [
            'user_id'       => $uid,
            'jti'           => $jti,
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



}
