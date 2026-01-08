<?php
namespace Service;

require_once LSFPATH . '/lib/php-jwt/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lsf\Env;

/**
 * 用户服务
 * $Id: user.php $
 * @author mengrui
 */
class User extends Base
{
    const REDIS_KEY_ACCESS_TOKEN_DATA    = 'voice-note-service:check_access_token:';
    const REDIS_EXPIRE_TIME             = 3600;
    /**
     * @var mixed
     */
    private $_svrDaoVnUserModel;
    private $_svrDaoVnUserAuthModel;
    private $_svrDaoVnUserSessionsModel;
    private $_svrDaoVnUserInfoModel;
    private $_svrDaoVnUserLogsModel;
    private $_uploadService;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        $this->_svrDaoVnUserModel = \Lsf\Loader::model('DaoVnUser', true);
        $this->_svrDaoVnUserAuthModel = \Lsf\Loader::model('DaoVnUserAuth', true);
        $this->_svrDaoVnUserSessionsModel = \Lsf\Loader::model('DaoVnUserSessions', true);
        $this->_svrDaoVnUserInfoModel = \Lsf\Loader::model('DaoVnUserInfo', true);
        $this->_svrDaoVnUserLogsModel = \Lsf\Loader::model('DaoVnUserLogs', true);
        $this->_uploadService = \Lsf\Loader::service('Upload', true);
    }

    /**
     * 用户退出
     * @param   string   $jti   会话唯一标志
     * @return mixed
     */
    public function logout($jti)
    {
        $result = $this->_svrDaoVnUserSessionsModel->changeSessionStatusByJti($jti);
        // 数据库操作失败
        if($result === FALSE){
            return -7;
        }

        //同时更新redis登录状态为失效
        $redisKey  = self::REDIS_KEY_ACCESS_TOKEN_DATA.$jti;
        $cacheResult = \Lsf\Loader::plugin('RedisPool')->redis()->setex($redisKey, self::REDIS_EXPIRE_TIME , 2);
        if(!$cacheResult){
            \Lsf\Loader::plugin('Log')->error(100001510, [
                'redis_key'     => $redisKey,
                'call_function' => 'setex',
                'result'        => $cacheResult
            ]);
        }
        return $result;
    }

    /**
     * 使用 refresh_token 刷新 access_token
     * @param $refreshToken string
     * @return void
     */
    public function refreshAccessToken(string $refreshToken){
        //1. 验证 refresh_token 是否为合法
        $payload = $this->verifyRefreshToken($refreshToken);
        if(!$payload) return -1;

        $userId = $payload['sub'];
        $jti = $payload['jti'];

        //2. 去数据库查 user_sessions 是否注销 todo 可以考虑加redis缓存（优先查询redis）
        $result = $this->_svrDaoVnUserSessionsModel->findSessionByJti($jti, 'status, is_deleted');
        if($result === false){
            return -7; //数据库操作失败
        }
        if(isset($result[0]['status']) && isset($result[0]['is_deleted'])){
            if((int)$result[0]['is_deleted'] === 1){  //已注销
                return -4;
            }
            if((int)$result[0]['status'] === 2){ //已登出
                return -5;
            }

        }else{
            return -3; // 数据异常
        }
        //3. 颁发新的 access_token & refresh_token
        $result_token = $this->generateTokens($userId);
        if(!isset($result_token['access_token']) || !isset($result_token['refresh_token']) || !isset($result_token['expires_at'])) {
            \Lsf\Loader::plugin('Log')->error(1002008, [
                'call' => 'generateTokens',
                'result' => $result_token,
                'message' => 'generate jwt token failed',
            ]);
            return -6; // access token 获取失败
        }
        //4. UPDATE 一条记录user_sessions
        $data = [
            'jti' => $result_token['jti'],
            'session_token' => $result_token['access_token'],
            'refresh_token' => $result_token['refresh_token'],
            'expire_at' => date('Y-m-d H:i:s', $result_token['expires_at']),
            'refresh_expires_at' => date('Y-m-d H:i:s', $result_token['refresh_exp']),
        ];
        $where = [
            'jti' => $jti,
        ];
        $result_session = $this->_svrDaoVnUserSessionsModel->updateSession($data , $where);
        if($result_session === false){
            return -7; //数据库操作失败
        }
        return $data;
    }

    /**
     * 根据uid查询用户信息
     * @param int $uid
     * @return void
     */
    public function getUserInfo($uid){
        // 要查询的字段
        $col = 'nickname, email, gender, avatar_url, timezone, language';
        $result = $this->_svrDaoVnUserInfoModel->findUserInfo($col, $uid);
        // 数据库操作失败
        if($result === FALSE){
            return -7;
        }
        if(isset($result[0]['avatar_url']) && !empty($result[0]['avatar_url'])){
            $result[0]['avatar_url'] = $this->_uploadService->getSignUrl($result[0]['avatar_url']);
            if($result[0]['avatar_url'] === false){ //  头像文件签名失败
                $result[0]['avatar_url'] = '';
            }
        }
        return $result;
    }

    /**
     * 根据uid编辑用户信息
     * @param int $uid
     * @param array $user_info
     * @return void
     */
    public function editUserInfo($uid, $user_info){
        //更新数据
        $result = $this->_svrDaoVnUserInfoModel->editUserInfo($uid, $user_info);
        // 数据库操作失败
        if($result === FALSE){
            return -7;
        }
        //查询数据
        $col = 'nickname, gender, avatar_url, timezone, language, updated_at';
        $user_info = $this->_svrDaoVnUserInfoModel->findUserInfo($col,$uid);
        // 数据库操作失败
        if($user_info === FALSE){
            return -7;
        }
        return $user_info;
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
                'status' => 0, //状态失效/禁用
            ];
            $where = [
                'user_id' => $uid,
            ];

            $this->_svrDaoVnUserSessionsModel->begin();
            $resultSession = $this->_svrDaoVnUserSessionsModel->updateSession($data , $where);
            if($resultSession === false){
                $this->_svrDaoVnUserSessionsModel->rollback();
                return -7;
            }

            //2. 用户第三方绑定信息失效
            $resultAuth = $this->_svrDaoVnUserAuthModel->updateOauth($data , $where);
            if($resultAuth === false){
                $this->_svrDaoVnUserSessionsModel->rollback();
                return -7;
            }

            //3. 用户扩展资料信息失效
            $dataInfo = [
                'is_deleted' => 1, //注销
            ];
            $resultInfo = $this->_svrDaoVnUserInfoModel->updateUserInfo($dataInfo , $where);
            if($resultInfo === false){
                $this->_svrDaoVnUserSessionsModel->rollback();
                return -7;
            }

            //4. 主表信息失效
            $where = [
                'id' => $uid,
            ];
            $resultUser = $this->_svrDaoVnUserModel->deleteUser($data , $where);
            if($resultUser === false){
                $this->_svrDaoVnUserSessionsModel->rollback();
                return -7;
            }
            $this->_svrDaoVnUserSessionsModel->commit();
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
            //log日志
            $this->_svrDaoVnUserLogsModel->storeLogs($data);
            return 0;
        }catch (\Exception $e){ //数据库操作失败
            \Lsf\Loader::plugin('Log')->error(1001014,
                [
                    'call_function' => 'cancellation',
                    'uid' => $uid,
                    'params' => $params,
                    'error' => $e,
                ]);
            return -2;
        }

    }

    /**
     * 用户头像上传
     * @param int $uid
     * @param array $fileInfo
     * @param string $scene
     * @return void
     */
    public function uploadAvatar($uid, $fileInfo, $scene){
        $url = $this->_uploadService->uploadFileOss($fileInfo, $scene);
        if($url === false){ //上传失败
            return -1;
        }
        $data = [
            'avatar_url' => $url,
        ];
        $where = ['user_id' => $uid];
        $result = $this->_svrDaoVnUserInfoModel->update($data , $where);
        if($result === false){
            return -7;
        }
        $url = $this->_uploadService->getSignUrl($url);
        if($url === false){ //  头像文件签名失败
            return -2;
        }
        return $url;
    }

    /**
     * 检查用户是否为游客身份
     * @param int $uid
     * @return void
     */
    public function isGuest($uid){
        $columns = 'is_guest';
        $result = $this->_svrDaoVnUserModel->find($columns, $uid);
        if($result === false){
            return -7;
        }
        if(isset($result['is_guest']) && (int)$result['is_guest'] === 1){
            return true;
        }
        return false;
    }
    /*
     * 设置缓存
     * @param  string  $redisKey
     * @param  array   $data
     * @param  int     $expire
     * @return bool
     */
    /**
     * @param $redisKey
     * @param $data
     * @param $expire
     */
    private function _setCache($redisKey, $data, $expire = 0)
    {
        try {
            // 默认过期时间
            if ( ! $expire) {
                $expire = self::REDIS_EXPIRE_TIME;
            }
            $result = \Lsf\Loader::plugin('RedisPool')->redis()->setex($redisKey, $expire, $data);
            if ( ! $result) {
                \Lsf\Loader::plugin('Log')->error(1002007, [
                    'redis_key'     => $redisKey,
                    'call_function' => 'setex',
                    'result'        => $result,
                ]);

                return false;
            } else {
                return true;
            }
        } catch (\RedisException $e) {
            \Lsf\Loader::plugin('Log')->error(1002007, [
                'redis_key'     => $redisKey,
                'call_function' => 'setex',
                'code'          => $e->getCode(),
                'message'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 读取缓存
     * @param  string  $redisKey
     * @return mixed
     */
    private function _getCache($redisKey)
    {
        try {
            $redisData = \Lsf\Loader::plugin('RedisPool')->redis()->get($redisKey);
            if ( ! empty($redisData)) {
                return $redisData;
            }

            return '';
        } catch (\RedisException $e) {
            \Lsf\Loader::plugin('Log')->error(1002007, [
                'redis_key'     => $redisKey,
                'call_function' => 'get',
                'code'          => $e->getCode(),
                'message'       => $e->getMessage(),
            ]);

            return false;
        }
    }

}
