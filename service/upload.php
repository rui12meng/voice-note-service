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


    public function updateAvatarOss($uid, $files_info){

        // ===== 生成唯一文件名 =====
        $ext = pathinfo($files_info['name'], PATHINFO_EXTENSION);
        //$objectKey = 'user/avatar/' . uniqid() . '.' . $ext; // 路径：user/avatar/65d8a1b2c3e4f.jpg
        $objectKey = uniqid() . '.' . $ext;

        //===== 上传到 OSS =====
        try {
            $ossClient = new OssClient($this->_aliyunOssConfig['access_key_id'], $this->_aliyunOssConfig['access_key_secret'], $this->_aliyunOssConfig['end_point']);
            // 上传文件
            $ossClient->uploadFile($this->_aliyunOssConfig['bucket'], $objectKey, $files_info['tmp_name']);

            // ===== 生成访问 URL =====
            // 方式 A：公开读 Bucket（不推荐，仅演示）
            // $url = "https://{$bucket}.{$endpoint}/{$objectKey}";

            // 方式 B：私有 Bucket + 临时签名 URL（推荐！有效期 1 小时）
            //$url = $ossClient->signUrl(self::OSS_BUCKET, $objectKey, 3600); // 3600秒 = 1小时
            //echo $url;exit();

            // 7. 构造公开访问 URL
            $publicUrl = "https://".$this->_aliyunOssConfig['bucket'].".".$this->_aliyunOssConfig['end_point']."/" . rawurlencode($objectKey);
            var_dump($publicUrl);exit();
            // 返回结果
            echo json_encode([
                'code' => 200,
                'message' => 'Upload success',
                'data' => [
                    'avatar_url' => $publicUrl,
                    'object_key' => $objectKey
                ]
            ]);

        } catch (OssException $e) {
            //error_log("OSS Error: " . $e->getMessage());
            //http_response_code(500);
            echo json_encode(['error' => 'OSS upload failed']);
        } catch (Exception $e) {
            //error_log("General Error: " . $e->getMessage());
            //http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }

    }
}
