<?php
namespace User\Controller;

/**
 * 用户控制器
 * $Id: member.php $
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
        $this->_userService = \Lsf\Loader::service('User', false, APP_NAME_USER);
        $this->_uploadService = \Lsf\Loader::service('Upload', true);
    }

    /**
     * 刷新访问令牌
     * @param  void
     * @return string
     */
    public function refresh_token(){
        $token = $this->post('token', true);
        if ( ! isset($token) || empty($token)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        $uid = $this->post('uid', true);
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'uid');
        }

        $returnData = $this->_ucService->tokenRefreshService($uid, $token);

        return $this->json($returnData['ecode'], $returnData['data'], $returnData['emsg']);
    }

    /**
     * 用户退出登录
     * @param  void
     * @return string
     */
    public function logout(){
        // 参数校验
        $uid = $this->post('uid', true);
        if (empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'uid');
        }
        $token = $this->post('token', true);
        if (empty($token)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $result = $this->_userService->userLogoutService($uid, $token);

        return $this->json($result['code'], $result['data'], $result['msg']);

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
     * 用户上传头像
     * @param  void
     * @return string
     */
    /*public function upload_avatar()
    {
        // 用户id
        $uid = $this->post('uid', true);
        if (empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'uid');
        }
        // token
        $token = $this->post('token', true);
        if (empty($token)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        // 头像信息
        $filesInfo = $this->files('avatar', true);
        if (empty($filesInfo['tmp_name']) || empty($filesInfo['size'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'avatar');
        }
        $fp     = fopen($filesInfo['tmp_name'], "rb");
        $as     = fread($fp, $filesInfo['size']);
        $avatar = base64_encode($as);

        // 角色
        $userType = $this->post('usertype', true);

        $result = $this->_userService->updateAvatar($uid, $token, $avatar, $userType);

        $eCode = ECODE_SUCCESS;
        $eMsg  = '';
        if (is_int($result) && $result < 0) {
            switch ($result) {
                //接口网络请求失败
                case -1:
                case -2:
                    $eCode = ECODE_API_NETWORK_REQUEST_FAIL;
                    break;
                //接口响应数据异常
                case -3:
                case -4:
                case -5:
                    $eCode = ECODE_API_RESPONSE_DATA_EXCEPTION;
                    break;
                //上行参数异常
                case -101;
                    $eCode = 9018114;
                    break;
                //图片鉴定为黄色
                case -102;
                    $eCode = ECODE_PIC_YELLOW;
                    $eMsg  = '头像更改失败，内容涉嫌违规';
                    break;
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
                    break;
            }
        }

        return $this->json($eCode, $result, $eMsg);
    }*/

    /**
     * 查询用户个人信息
     * @param  void
     * @return string
     */
    public function info()
    {
        $result = [];

        return $this->json(ECODE_SUCCESS, $result);
    }

    /**
     * 更新用户个人信息
     * @param  void
     * @return string
     */
    public function edit_profile()
    {
        $uid = '';
        // token
        $token = $this->post('token', true);
        if (empty($token)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        //token 解析uid

        // 用户信息
        $nickName = $this->post('nickname', true); //昵称
        $gender = $this->post('gender', true); //性别
        $language = $this->post('language', true); //语言

        $userInfo = [];
        if(isset($nickName) && !empty($nickName)) {
            $userInfo['nickname'] = $nickName;
        }
        if(isset($gender) && !empty($gender)) {
            $userInfo['gender'] = $gender;
        }
        if(isset($language) && !empty($language)) {
            $userInfo['language'] = $language;
        }


        $result      = $this->_userService->modifyUserInfo($token, $uid, $userInfo);
        switch ($result['ecode']) {
            case 0:
                return $this->json(ECODE_SUCCESS, $result['data']);
                break;
            default:
                return $this->json(9018103, $result);
                break;
        }

        return $this->json(ECODE_SUCCESS, $result);
    }

    /**
     * 用户注销
     * @param  void
     * @return string
     */
    public function cancellation()
    {
        // 参数处理
        $token = $this->post('token', true);
        if ( ! isset($token) || empty($token)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        // 注销
        $result = $this->_userService->cannellation($this->uid);
        switch ($result) {
            // 注销成功
            case 0:
                $eCode = ECODE_SUCCESS;
                break;
            case -5:
                $eCode = 9013009;
                break;
            // 注销失败
            case -7:
                $eCode = 9013001;
                break;
            // 未知错误
            default:
                $eCode = $this->erroneous($result);
        }

        return $this->json($eCode, $result);
    }

}
