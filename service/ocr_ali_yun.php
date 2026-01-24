<?php
namespace Service;

require_once LSFPATH . '/lib/ocr-api-20210707-master/autoload.php';

use AlibabaCloud\SDK\Ocrapi\V20210707\Ocrapi;

/**
 * 阿里云OCR识别服务
 * $Id: OcrAliYun.php $
 * @author mengrui
 */

class OcrAliYun
{
    private $_aliYunConfig;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        $this->_aliYunConfig =\Lsf\Env::group('ALIYUN_OSS_');
    }

    /**
     * 文字识别
     * @param   string $file
     * @return void
     */
    public function Ocr($file){
        try {
            $config = [
                'accessKeyId' => $this->_aliYunConfig['access_key_id'],
                'accessKeySecret' => $this->_aliYunConfig['access_key_secret'],
                'endpoint' => 'ocr.cn-shanghai.aliyuncs.com',
            ];

            $client = new Ocrapi($config);
            $request = [
                'imageUrl' => $file,
                'languageType' => 'AUTO',
            ];

            $response = $client->recognizeGeneral($request);
            $body = $response->body;


            if (isset($body->data) && is_string($body->data)) {
                $data = json_decode($body->data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $body->data = $data;
                }
            }
            return $body;
        } catch (\Exception $e) {
            \Lsf\Loader::plugin('Log')->error('AliYun OCR SDK Error: ' . $e->getMessage(), [], 'ocr_aliYun');
            return false;
        }
    }


}
