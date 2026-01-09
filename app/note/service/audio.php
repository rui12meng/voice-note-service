<?php
namespace Note\Service;

/**
 * 音频文件服务
 * @author mengrui
 * $Id: audio.php $
 */

class Audio
{
    private $_uploadService;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_uploadService = \Lsf\Loader::service('Upload', true);
    }

    /**
     * 用户笔记音频文件上传
     * @param int $uid
     * @param array $audioInfo
     * @param string $scene
     * @return void
     */
    public function uploadAudio($audioInfo, $scene = 'audio'){

        //上传OSS
        $result = $this->_uploadService->uploadFileOss($audioInfo, $scene);
        return $result;
    }

    /**
     * 验证上传的音频文件是否符合要求
     * @param array     $file  原始文件
     * @param array     $options   配置选项
     *     - max_size: 最大文件大小（字节），默认 10MB
     *     - max_duration: 最大时长（秒），默认 600
     *     - allowed_mimes: 允许的 MIME 类型列表
     * @return void
     */
    public function validateAudio(array $file, array $options = [])
    {
        // 默认配置
        $config = array_merge([
            'max_size'      => 10 * 1024 * 1024, // 10MB
            'allowed_ext'   => ['mp3', 'wav', 'm4a', 'aac', 'ogg'],
            'allowed_mime'  => [
                'audio/mpeg',
                'audio/wav',
                'audio/x-wav',
                'audio/mp4',
                'audio/aac',
                'audio/ogg',
            ],
            'max_duration'  => 600, // 秒，10分钟
        ], $options);


        //文件大小限制
        if ($file['size'] > $config['max_size']) {
            \Lsf\Loader::plugin('Log')->error(1003501, ['file_size' => $file['size'],'error' => '音频文件大小超过限制']);
            return -1;
        }

        //扩展名校验
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $config['allowed_ext'], true)) {
            \Lsf\Loader::plugin('Log')->error(1003502, ['file_name' => $file['name'],'error' => '不支持的文件类型']);
            return -2;
        }

        //MIME 类型校验
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $config['allowed_mime'], true)) {
            \Lsf\Loader::plugin('Log')->error(1003503, ['tmp_name' => $file['tmp_name'],'error' => '非法音频文件']);
            return -3;
        }

        $duration = $this->_get($file['tmp_name']);
        if ($duration > $config['max_duration']) {
            \Lsf\Loader::plugin('Log')->error(1003504, [
                'tmp_name' => $file['tmp_name'],
                'duration' => (float)$duration,
                'error' => '音频时长超出限制'
            ]);
            return -6;
        }
        return [
            'duration' => (float)$duration
        ];
    }

    /**
     * 获取音频时长（秒）
     * @param string $file
     * @return void
     */
    private static function _get(string $file)
    {
        if (!is_file($file)) {
            return 0;
        }

        $cmd = sprintf(
            'ffprobe -i %s -show_entries format=duration -v quiet -of csv="p=0"',
            escapeshellarg($file)
        );

        $output = shell_exec($cmd);

        if ($output === null) {
            return 0;
        }

        return (float)$output;
    }
}