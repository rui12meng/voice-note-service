<?php
namespace Service;


/**
 * 火山 OCR识别服务
 * $Id: OcrVolc.php $
 * @author mengrui
 */

class OcrVolc extends \Model\SvrBase
{
    private $_volcConfig;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_volcConfig =\Lsf\Env::group('VOLC_ASR_');
    }

    /**
     * 文字识别
     * @param   string $file
     * @return void
     */
    public function Ocr(string $file){
        try {

            //$imageBase64 = base64_encode(file_get_contents(WEBPATH.'/test2.jpeg'));
$url = 'https://pics0.baidu.com/feed/b8389b504fc2d5628c9e35489426aae277c66c41.jpeg';

            // 1. 构造原始请求参数（不含 Signature）
            $params = [
                //'image_base64'      => $imageBase64,
                'image_url' => $url,
            ];
            $body= json_encode($params, JSON_UNESCAPED_UNICODE);

            // 2. 生成签名
            $signature = self::sign(
                $this->_volcConfig['app_key'],
                $this->_volcConfig['access_key'],
                $region='cn-north-1',
                $service = 'iam',
                $action = 'OCRNormal',
                $body);

            $headers = $signature;

            // 3. 发送请求
            $apiSign = 'volc_ocr';
            $response = $this->post($apiSign, $params, $headers , 4);
            var_dump($response);exit();



        } catch (\Exception $e) {
            \Lsf\Loader::plugin('Log')->error('AliYun OCR SDK Error: ' . $e->getMessage(), [], 'ocr_aliYun');
            return false;
        }
    }

    /**
     * 生成火山引擎 API 请求的 Authorization 头
     *
     * @param string $accessKeyId      Access Key ID
     * @param string $secretAccessKey  Secret Access Key
     * @param string $region           区域（如 cn-beijing）
     * @param string $service          服务名（如 ocr）
     * @param string $action           接口动作（如 HandwritingRecognition）
     * @param string $body             请求体（JSON 字符串）
     * @param array  $extraHeaders     额外 headers（可选）
     * @return array [headers => [...], signedHeaders => "x-action;x-date;..."]
     */
    public static function sign(
        string $accessKeyId,
        string $secretAccessKey,
        string $region,
        string $service,
        string $action,
        string $body,
        array $extraHeaders = []
    ) {
        // 1. 当前 UTC 时间
        $date = gmdate('Ymd\THis\Z');

        // 2. 构造基础 headers
        $headers = array_merge([
            'Host' => 'ocr.volcengineapi.com',
            'Content-Type' => 'application/json',
            'X-Date' => $date,
        ], $extraHeaders);

        $query = [
            'Action' => $action,
            'Version' => '2020-08-26',
        ];
        ksort($query);
        $requestParam = [
            // body是http请求需要的原生body
            'body' => $body,
            'host' => 'iam.volcengineapi.com', //$Host,
            'path' => '/',
            'method' => 'POST',
            'contentType' => 'application/x-www-form-urlencoded',
            'date' => $date,
            'query' => $query
        ];
        // 第三步：接下来开始计算签名。在计算签名前，先准备好用于接收签算结果的 signResult 变量，并设置一些参数。
        // 初始化签名结果的结构体
        $xDate = $requestParam['date'];
        $shortXDate = substr($xDate, 0, 8);
        $xContentSha256 = hash('sha256', $requestParam['body']);
        $signResult = [
            'Host' => $requestParam['host'],
            'X-Content-Sha256' => $xContentSha256,
            'X-Date' => $xDate,
            'Content-Type' => $requestParam['contentType']
        ];
        // 第四步：计算 Signature 签名。
        $signedHeaderStr = join(';', ['content-type', 'host', 'x-content-sha256', 'x-date']);
        $canonicalRequestStr = join("\n", [
            $requestParam['method'],
            $requestParam['path'],
            http_build_query($requestParam['query']),
            join("\n", ['content-type:' . $requestParam['contentType'], 'host:' . $requestParam['host'], 'x-content-sha256:' . $xContentSha256, 'x-date:' . $xDate]),
            '',
            $signedHeaderStr,
            $xContentSha256
        ]);
        $hashedCanonicalRequest = hash("sha256", $canonicalRequestStr);
        $credentialScope = join('/', [$shortXDate, $region, $service, 'request']);
        $stringToSign = join("\n", ['HMAC-SHA256', $xDate, $credentialScope, $hashedCanonicalRequest]);
        $kDate = hash_hmac("sha256", $shortXDate, $secretAccessKey, true);
        $kRegion = hash_hmac("sha256", $region, $kDate, true);
        $kService = hash_hmac("sha256", $service, $kRegion, true);
        $kSigning = hash_hmac("sha256", 'request', $kService, true);
        $signature = hash_hmac("sha256", $stringToSign, $kSigning);
        $signResult['Authorization'] = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s", $accessKeyId . '/' . $credentialScope, $signedHeaderStr, $signature);
        $header = array_merge($headers, $signResult);

        return $header;
    }

}
