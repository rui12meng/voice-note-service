<?php
namespace Note\Controller;

/**
 * 笔记控制器
 * $Id: notes.php $
 * @author mengrui
 */

class Notes extends \App\Application
{
    /**
     * @var mixed
     */
    private $_svrAudio;
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
        $this->_svrAudio = \Lsf\Loader::service('SvrAudio', false, APP_NAME_NOTE);
        $this->_uploadService = \Lsf\Loader::service('Upload', true);
    }

    /**
     * 语音笔记上传
     * @param  void
     * @return void
     */
    public function addAudio()
    {
        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $audio_info = $this->files('audio', true);

        //文件是否存在
        if (empty($audio_info) || !isset($audio_info['tmp_name']) || empty($audio_info['size'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'audio');
        }

        //上传错误
        /*if ($audio_info['error'] !== UPLOAD_ERR_OK  || $audio_info['size'] === 0) {
            throw new Exception("Invalid or empty audio");
        }*/

        $response = $this->_svrAudio->validateAudio($audio_info);
        /*if (is_int($response) && $response < 0) {
            switch ($response) {
                case -1://音频文件大小超过限制
                    $eCode = 9043020;
                    break;
                case -2:
                case -3: //类型错误
                    $eCode = 9043034;
                    break;
                case -4: //文件不是有效音频 //无法解析音频时长
                    $eCode = 9043035;
                    break;
                case -5: //无法解析音频时长 //音频时长超出限制
                    $eCode = 9043035;
                    break;
                case -6: //音频时长超出限制 //音频解析失败
                    $eCode = 9043035;
                    break;
                case -7: //音频解析失败
                    $eCode = 9043035;
                    break;
                // 未知错误
                default:
                    $eCode = $this->erroneous($response);
                    break;
            }
        } else {
            $result = isset($response['duration']) ?? '';
        }*/
        if(is_array($response)&& isset($response['duration'])){
            //上传OSS
            $url = $this->_uploadService->uploadFileOss($uid, $scene = 'audio', $audio_info);

            var_dump($url);exit();
        }

        $response = [];
        return $this->json(ECODE_SUCCESS, $response);
    }
}
