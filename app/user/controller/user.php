<?php
namespace User\Controller;

/**
 * 用户控制器
 * $Id: user.php $
 * @author mengrui
 */
class User extends \App\Application
{

    /**
     * @var mixed
     */
    private $_userService;
    private $_uploadService;

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
        $this->_userService = \Lsf\Loader::service('User', true);
        $this->_uploadService = \Lsf\Loader::service('Upload', true);
    }

    /**
     * 刷新访问令牌（登录态刷新）
     * @param  void
     * @return string
     */
    public function tokenRefresh(){
        $result = [];
        $refresh_token = $this->post('refresh_token', true);
        if ( ! isset($refresh_token) || empty($refresh_token)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'refresh_token');
        }

        $token_info = $this->_userService->refreshAccessToken($refresh_token);

        $eCode = ECODE_SUCCESS;
        $result = [];
        if (is_int($token_info) && $token_info < 0) {
            switch ($token_info) {
                case -1: //refresh token 非法
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                case -2: //数据库操作失败
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                case -3: //数据不存在
                    $eCode = ECODE_DATA_NOT_FOUND;
                    break;
                case -4: //注销
                    $eCode = ECODE_USER_ACCOUNT_DEACTIVATED;
                    break;
                case -5: //登出
                    $eCode = ECODE_USER_LOGGED_OUT;
                    break;
                case -6: //access token 获取失败
                    $eCode = ECODE_TOKEN_GENERATE_FAILED;
                    break;
                // 未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        } else {
            $result['token'] = $token_info['access_token'] ?? '';
            $result['refresh_token'] = $token_info['refresh_token'] ?? '';
            $result['expires_in'] = $token_info['expires_at'] ?? '';
        }

        return $this->json($eCode, $result);
    }

    /**
     * 登出（退出登录）
     * @param  void
     * @return string
     */
    public function logout(){
        //1. 解析 access_token 取 uid;不需要校验 exp 是否过期(入口文件已实现)
        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        $jti = $this->jti;
        if ( ! isset($jti) || empty($jti)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        // 2. refresh_token 必须删除或失效化
        $result = $this->_userService->logout($jti);

        if (is_int($result) && $result < 0) {
            switch ($result) {
                // 数据库操作失败
                case -1:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                // 未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        } else {
            $eCode = ECODE_SUCCESS;
        }
        return $this->json($eCode, []);
    }

    /**
     * 获取用户信息
     * @param  void
     * @return string
     */
    public function getProfile(){
        $result = [];
        //token
        $uid = $this->uid;
        if (empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'invalid token');
        }
        $userInfo = $this->_userService->getUserInfo($uid);
        $eCode = ECODE_SUCCESS;
        if (is_int($userInfo) && $userInfo < 0) {
            switch ($userInfo) {
                // 数据库操作失败
                case -1:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                // 未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        } else {
            $result = [
                'nickname' => $result[0]['nickname'] ?? '',
                'gender' => $result[0]['gender'] ?? 0,
                'avatar_url' => $result[0]['avatar_url'] ?? '',
                'timezone' => $result[0]['timezone'] ?? '',
                'language' => $result[0]['language'] ?? '',
            ];
        }
        return $this->json($eCode, $result);
    }

    /**
     * 更新用户信息
     * @param  void
     * @return string
     */
    public function editProfile(){
        $uid = $this->uid;
        if (empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'invalid token');
        }

        $user_info = [];

        // 用户信息-昵称
        $nickname = $this->post('nickname', true);
        if (!empty($nickname)) {
            $user_info['nickname'] = $nickname;
        }
        // 用户信息-性别
        $gender = $this->post('gender', true);
        if (!empty($gender)) {
            $user_info['gender'] = $gender;
        }
        // 用户信息-时区
        $timezone = $this->post('timezone', true);
        if (!empty($timezone)) {
            $user_info['timezone'] = $timezone;
        }
        // 用户信息-语言
        $language = $this->post('language', true);
        if (!empty($timezone)) {
            $user_info['language'] = $language;
        }

        if(is_array($user_info) && count($user_info) > 0){
            $uInfo = [];
            $eCode = ECODE_SUCCESS;
            $result = $this->_userService->editUserInfo($uid, $user_info);
            if (is_int($result) && $result < 0) {
                switch ($result) {
                    // 数据库操作失败
                    case -1:
                        $eCode = ECODE_DATABASE_QUERY_FAIL;
                        break;
                    // 未知错误
                    default:
                        $eCode = ECODE_UNDEFINED_ERROR;
                }
            }else{
                $uInfo = [
                    'nickname' => $result['nickname'] ?? '',
                    'gender' => $result['gender'] ?? 0,
                    'avatar_url' => $result['avatar_url'] ?? '',
                    'timezone' => $result['timezone'] ?? '',
                    'language' => $result['language'] ?? '',
                    'update_at' => $result['update_at'] ?? '',
                ];
            }
            return $this->json($eCode, $uInfo);

        }else{
            //没有要修改的内容
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'miss user info');
        }
    }

    /**
     * 用户设置/修改头像
     * @author mengrui
     *
     * @param  void
     * @throws \Exception
     * @return string
     */
    public function updateAvatar()
    {
        // 用户id
//        $uid = $this->uid;
//        if (empty($uid)) {
//            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
//        }
        $uid = 1;
        // 头像信息
        $files_info = $this->files('avatar', true);

        if ($files_info['error'] !== UPLOAD_ERR_OK || $files_info['size'] === 0) {
            throw new Exception("Invalid or empty file");
        }

        if (empty($files_info['tmp_name']) || empty($files_info['size'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'avatar');
        }
//        $fp = fopen($filesInfo['tmp_name'], "rb");
//        $as = fread($fp, $filesInfo['size']);
//        $avatar = base64_encode($as);

// || $filesInfo['error'] !== UPLOAD_ERR_OK

        // 校验文件类型（MIME）
        $f_info = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($f_info, $files_info['tmp_name']);
        finfo_close($f_info);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif']; // 允许的 MIME 类型
        if (!in_array($mimeType, $allowedMimes)) {
            //格式错误
            echo json_encode(['error' => 'Only JPG/PNG/GIF allowed']);
            exit;
        }

        // 校验文件大小（2MB）
        if ($files_info['size'] > 2 * 1024 * 1024) {
            //文件超过限制
            http_response_code(400);
            echo json_encode(['error' => 'File too large (max 5MB)']);
            exit;
        }



        $result = $this->_uploadService->updateAvatarOss($uid, $files_info);

    }

    /**
     * 用户注销
     * @param  void
     * @return string
     */
    public function cancellation()
    {
        $data = $this->post('');
        // 参数处理
        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'invalid token');
        }

        // 注销
        $result = $this->_userService->cancellation($uid , $data);

        switch ($result) {
            // 注销成功
            case 0:
                $eCode = ECODE_SUCCESS;
                break;
            case -1: //部分失败
                $eCode = 9013009;
                break;
            // 注销失败
            case -2:
                $eCode = 9013001;
                break;
            // 未知错误
            default:
                $eCode = $this->erroneous($result);
        }

        return $this->json($eCode, $result);
    }

}
