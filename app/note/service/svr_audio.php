<?php
namespace Note\Service;

//require_once LSFPATH . '/lib/getID3/autoload.php';

//use JamesHeinrich\GetID3\GetID3;

/**
 * 语音笔记服务
 * @author mengrui
 * $Id: svr_audio.php $
 */

class SvrAudio
{
    private $_daoVnNotesModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        //$this->_daoVnNotesModel = \Lsf\Loader::model('DaoVnNotes',TRUE);
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
    public function validateAudio(array $file, array $options = []): array
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
            \Lsf\Loader::plugin('Log')->error(9011500, ['file_size' => $file['size'],'error' => '音频文件大小超过限制']);
            return -1;
        }

        //扩展名校验
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $config['allowed_ext'], true)) {
            \Lsf\Loader::plugin('Log')->error(9011500, ['file_name' => $file['name'],'error' => '不支持的文件类型']);
            return -2;
        }

        //MIME 类型校验
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $config['allowed_mime'], true)) {
            \Lsf\Loader::plugin('Log')->error(9011500, ['tmp_name' => $file['tmp_name'],'error' => '非法音频文件']);
            return -3;
        }

        $duration = $this->get($file['tmp_name']);
        if ($duration > $config['max_duration']) {
            \Lsf\Loader::plugin('Log')->error(9011500, [
                'tmp_name' => $file['tmp_name'],
                'duration' => (float)$duration,
                'error' => '音频时长超出限制'
            ]);
            return -6;
        }
        return [
            'duration' => (float)$duration
        ];
        //var_dump($r);exit();
        //音频时长校验
        /*try {
            $getID3 = new GetId3();
            $fileInfo = $getID3->analyze($file['tmp_name']);

            // 确保是音频文件
            if (empty($fileInfo['audio'])) {
                \Lsf\Loader::plugin('Log')->error(9011500, [
                    'tmp_name' => $file['tmp_name'],
                    'file_getID3' => $fileInfo,
                    'error' => '文件不是有效音频'
                ]);
                return -4;
            }

            $duration = $fileInfo['playtime_seconds'] ?? null;
            if (!is_numeric($duration) || $duration <= 0) {
                \Lsf\Loader::plugin('Log')->error(9011500, [
                    'tmp_name' => $file['tmp_name'],
                    'file_getID3' => $fileInfo,
                    'error' => '无法解析音频时长'
                ]);
                return -5;
            }

            // 检查时长限制
            if ($duration > $config['max_duration']) {
                \Lsf\Loader::plugin('Log')->error(9011500, [
                    'tmp_name' => $file['tmp_name'],
                    'duration' => (float)$duration,
                    'error' => '音频时长超出限制'
                ]);
                return -6;
            }
            //$filename = uniqid('audio_', true) . '.' . $ext;
            return [
                'duration' => (float)$duration
            ];

        } catch (Exception $e) {
            \Lsf\Loader::plugin('Log')->error(9011500, [
                'tmp_name' => $file['tmp_name'],
                'error' => '音频解析失败:'.$e->getMessage(),
            ]);
            return -7;
        }*/
    }

    /**
     * 获取音频时长（秒）
     */
    public static function get(string $file): int
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