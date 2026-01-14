<?php
namespace Service;

require_once LSFPATH . '/lib/aliyun-oss-php-sdk/autoload.php';

use OSS\OssClient;
use OSS\Core\OssException;
use Lsf\Env;

/**
 * 上传服务
 * $Id: upload.php $
 * @author mengrui
 */

class Upload
{
    private $_aliyunOssConfig;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        $this->_aliyunOssConfig =\Lsf\Env::group('ALIYUN_OSS_');
    }

    /**
     * 上传文件(图片/音频)
     * @param   array $file_info
     * @param   string  $scene
     * @return void
     */
    public function uploadFileOss($file_info, $scene = 'avatar'){
        // ===== 生成唯一文件名 =====
        $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
        switch ($scene){
            case 'avatar';
                $path = 'user/avatar';
                break;
            case 'audio':
                $path = 'user/audio';
                break;
            default:
                $path = '';
                break;
        }
        $objectKey = $path.'/' . uniqid() . '.' . $ext; // 路径：user/avatar/65d8a1b2c3e4f.jpg

        var_dump($objectKey) ;
        // 上传到 OSS
        try {
            $ossClient = new OssClient($this->_aliyunOssConfig['access_key_id'], $this->_aliyunOssConfig['access_key_secret'], $this->_aliyunOssConfig['end_point']);
            // 上传文件
            $ossClient->uploadFile($this->_aliyunOssConfig['bucket'], $objectKey, $file_info['tmp_name']);

            // ===== 生成访问 URL =====
            // 方式 A：公开读 Bucket（不推荐，仅演示）
            // $url = "https://{$bucket}.{$endpoint}/{$objectKey}";

            // 方式 B：私有 Bucket + 临时签名 URL（推荐！有效期 1 小时）
            $signUrl = $ossClient->signUrl($this->_aliyunOssConfig['bucket'], $objectKey, 3600); // 3600秒 = 1小时
            var_dump($signUrl) ;
            // 7. 构造公开访问 URL
//            $publicUrl = "https://".$this->_aliyunOssConfig['bucket'].".".$this->_aliyunOssConfig['end_point']."/" . rawurlencode($objectKey);
            \Lsf\Loader::plugin('Log')->info('', [
                'file' => $file_info,
                'file_path' => $objectKey,
                'signUrl' => $signUrl,
            ]);
            // 返回结果
            $response = [
                'pathUrl' => $objectKey,
                'signUrl' => $signUrl,
            ];
            return $response;

        } catch (OssException $e) {
            var_dump($e);
            \Lsf\Loader::plugin('Log')->error(1002013, [
                'file' => $file_info,
                'file_path' => $objectKey,
                'error' => 'OSS Error:'.$e->getMessage(),
            ]);
            return false;
        } catch (Exception $e) {
            \Lsf\Loader::plugin('Log')->error(1002014, [
                'file' => $file_info,
                'file_path' => $objectKey,
                'error' => 'General Error:'.$e->getMessage(),
            ]);
            return false;
        }

    }

    /**
     * 文件(图片/音频)私有 Bucket + 临时签名 URL
     * @param   string  $pathUrl
     * @return void
     */
    public function getSignUrl($pathUrl){
        try {
            $ossClient = new OssClient($this->_aliyunOssConfig['access_key_id'], $this->_aliyunOssConfig['access_key_secret'], $this->_aliyunOssConfig['end_point']);

            // ===== 生成访问 URL =====
            // 方式 A：公开读 Bucket（不推荐，仅演示）
            // $url = "https://{$bucket}.{$endpoint}/{$objectKey}";

            // 方式 B：私有 Bucket + 临时签名 URL（推荐！有效期 1 小时）
            $url = $ossClient->signUrl($this->_aliyunOssConfig['bucket'], $pathUrl, 3600); // 3600秒 = 1小时

            return $url;

        } catch (OssException $e) {
            \Lsf\Loader::plugin('Log')->error(1002016, [
                'file_path' => $pathUrl,
                'error' => 'OSS Error:'.$e->getMessage(),
            ]);
            return false;
        } catch (Exception $e) {
            \Lsf\Loader::plugin('Log')->error(1002016, [
                'file_path' => $pathUrl,
                'error' => 'General Error:'.$e->getMessage(),
            ]);
            return false;
        }
    }
}
