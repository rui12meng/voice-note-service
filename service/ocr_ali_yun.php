<?php
namespace Service;

use phpDocumentor\Reflection\Types\Self_;

/**
 * 阿里云OCR识别服务
 * $Id: OcrAliYun.php $
 * @author mengrui
 */

class OcrAliYun extends \Model\SvrBase
{
    private $_aliYunConfig;
    const aliYun_regionId = 'cn-hangzhou';
    const aliYun_action = 'RecognizeGeneral';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_aliYunConfig =\Lsf\Env::group('ALIBABA_CLOUD_');
    }

    /**
     * 文字识别
     * @param   string $file
     * @return void
     */
    public function Ocr(string $file){
        try {
            $imageBase64 = base64_encode(file_get_contents(WEBPATH.'/test2.jpeg'));


            // 1. 构造原始请求参数（不含 Signature）
            $params = [
                'Action'           => self::aliYun_action,
                'Version'          => '2021-07-07',
                'Format'           => 'JSON',
                'RegionId'         => self::aliYun_regionId,
                'AccessKeyId'      => $this->_aliYunConfig['access_key_id'],
                'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
                'SignatureMethod'  => 'HMAC-SHA1',
                'SignatureVersion' => '1.0',
                'SignatureNonce'   => uniqid(microtime(false), true),
                'ImageBase64'      => $imageBase64,
                'LanguageType'     => 'AUTO',
            ];

            // 2. 生成签名
            $signature = self::generateSignature($params, $this->_aliYunConfig['access_key_secret']);
            $params['Signature'] = $signature;

            // 3. 发送请求
            //$endpoint = "http://ocr.{$regionId}.aliyuncs.com";
            $apiSign = 'ali_cloud_ocr';

            $response = $this->post($apiSign, $params, [] , 3);
var_dump($response);exit();



        } catch (\Exception $e) {
            \Lsf\Loader::plugin('Log')->error('AliYun OCR SDK Error: ' . $e->getMessage(), [], 'ocr_aliYun');
            return false;
        }
    }

    /**
     * 生成阿里云 RPC 风格 API 的签名
     *
     * @param array  $params          请求参数（包含 Action, Version, RegionId 等）
     * @param string $accessKeySecret AccessKey Secret
     * @return string 签名字符串（已 Base64 编码）
     */
    private static function generateSignature(array $params, string $accessKeySecret)
    {
        // 1. 对参数按键名进行字典序排序
        ksort($params);

        // 2. 构造规范化的查询字符串（RFC3986 编码）
        $canonicalizedQuery = '';
        foreach ($params as $key => $value) {
            $canonicalizedQuery .= '&' . self::percentEncode($key) . '=' . self::percentEncode((string)$value);
        }
        $canonicalizedQuery = ltrim($canonicalizedQuery, '&');

        // 3. 构造待签名字符串: METHOD + "&" + ENCODED_PATH + "&" + ENCODED_QUERY
        $stringToSign = "POST&%2F&" . self::percentEncode($canonicalizedQuery);

        // 4. 使用 HMAC-SHA1 计算签名（密钥 = AccessKeySecret + "&"）
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));

        return $signature;
    }

    /**
     * RFC3986 标准的 URL 编码（阿里云要求）
     * @param string $str
     * - 替换 ～!*() 为 %XX 形式
     * - 空格编码为 %20（不是 +）
     * @return void
     */
    private static function percentEncode(string $str)
    {
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        $res = preg_replace('/%7E/', '～', $res); // ～ 不编码
        return $res;
    }

    /**
     * 构造认证 Header
     * @return array
     */
    private function buildAuthHeaders()
    {
        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

}
