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
        $this->_volcConfig =\Lsf\Env::group('VOLC_OCR_');
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

            if (empty($this->_volcConfig['access_key_id']) || empty($this->_volcConfig['access_key_secret'])) {
                throw new \Exception('Volcengine OCR credentials not configured');
            }
            // 2. 生成签名
            $signature = self::sign(
                $this->_volcConfig['access_key_id'],
                $this->_volcConfig['access_key_secret'],
                $region='cn-north-1',
                $service = 'cv',
                $action = 'OCRNormal',
                $version = '2020-08-26',
                [],
                $params);

            $headers = $signature;

            $query = array_merge([], [
                'Action' => $action,
                'Version' => $version
            ]);
            ksort($query);
            $queryString = http_build_query($query);
            $requestUrl = 'https://visual.volcengineapi.com/?' . $queryString;

            $curlHeaders = [];
            foreach ($headers as $key => $value) {
                $curlHeaders[] = $key . ': ' . $value;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $requestUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $responseContent = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }
            curl_close($ch);

            print_r($responseContent);

            // 3. 发送请求
//            $apiSign = 'volc_ocr';
//            $response = $this->post($apiSign, $params, $headers , 4);
            //var_dump($response);exit();



        } catch (\Exception $e) {
            \Lsf\Loader::plugin('Log')->error('Volcengine OCR SDK Error: ' . $e->getMessage(), [], 'ocr_volc');
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
    /*public static function sign(
        string $accessKeyId,
        string $secretAccessKey,
        string $region,
        string $service,
        string $action,
        string $body,
        array $extraHeaders = []
    ) {

        $credential = [
        'accessKeyId' => $accessKeyId,
        'secretKeyId' => $secretAccessKey,
        'service' => $service,
        'region' => $region,
    ];

    // 初始化签名结构体
    $query = array_merge([], [
      'Action' => $action,
      'Version' => $version
    ]);

    ksort($query);
    $requestParam = [
        // body是http请求需要的原生body
        'body' => $body,
        'host' => $Host,
        'path' => '/',
        'method' => $method,
        'contentType' => $ContentType,
        'date' => gmdate('Ymd\THis\Z'),
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
    $credentialScope = join('/', [$shortXDate, $credential['region'], $credential['service'], 'request']);
    $stringToSign = join("\n", ['HMAC-SHA256', $xDate, $credentialScope, $hashedCanonicalRequest]);
    $kDate = hash_hmac("sha256", $shortXDate, $credential['secretKeyId'], true);
    $kRegion = hash_hmac("sha256", $credential['region'], $kDate, true);
    $kService = hash_hmac("sha256", $credential['service'], $kRegion, true);
    $kSigning = hash_hmac("sha256", 'request', $kService, true);
    $signature = hash_hmac("sha256", $stringToSign, $kSigning);
    $signResult['Authorization'] = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s", $credential['accessKeyId'] . '/' . $credentialScope, $signedHeaderStr, $signature);
    $header = array_merge($header, $signResult);
    // 第五步：将 Signature 签名写入 HTTP Header 中，并发送 HTTP 请求。
    $client = new Client([
        'base_uri' => 'https://' . $requestParam['host'],
        'timeout' => 120.0,
    ]);
    return $client->request($method, 'https://' . $requestParam['host'] . $requestParam['path'], [
        'headers' => $header,
        'query' => $requestParam['query'],
        'body' => $requestParam['body']
    ]);
    }*/

    /**
     * 生成 Authorization 头及完整请求头（用于 POST form 请求）
     *
     * @param string $accessKeyId      长期 Access Key ID（如 AKLTxxxxx）
     * @param string $secretAccessKey  Secret Access Key
     * @param string $region           区域（通用 OCR 固定为 'cn-north-1'）
     * @param string $service          服务名（通用 OCR 为 'cv'）
     * @param string $action           接口动作（如 'OCRNormal'）
     * @param string $version          API 版本（通用 OCR 为 '2020-08-26'）
     * @param array  $queryParams      Query 参数（除 Action/Version 外的额外参数，通常为空）
     * @param array  $bodyParams       Body 表单参数（如 ['image_base64' => '...']）
     * @return array {
     *      @var string $authorization  Authorization 头值
     *      @var string $xDate          X-Date 头值（UTC 时间）
     *      @var string $signedHeaders  已签名的 headers 列表（如 "content-type;host;x-date"）
     * }
     */
    public static function sign(
        string $accessKeyId,
        string $secretAccessKey,
        string $region,
        string $service,
        string $action,
        string $version,
        array $queryParams = [],
        array $bodyParams = []
    ) {
        // 1. 当前 UTC 时间
        $xDate = gmdate('Ymd\THis\Z');
        $shortDate = substr($xDate, 0, 8); // YYYY-MM-DD

        // 2. 构造 Query 参数（必须包含 Action 和 Version）
        $query = array_merge([
            'Action' => $action,
            'Version' => $version
        ], $queryParams);
        ksort($query);

        $queryString = http_build_query($query);

        // 3. 构造 Body 字符串（form-urlencoded）
        $bodyString = http_build_query($bodyParams);

        // 4. 构造 Headers（Content-Type + X-Date）
        $xContentSha256 = hash('sha256', $bodyString);
        $headers = [
            'Host' => 'visual.volcengineapi.com',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-Content-Sha256' => $xContentSha256,
            'X-Date' => $xDate,
        ];

        // 5. 规范化 Headers（转小写 + 按 key 排序）
        ksort($headers);
        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            $canonicalHeaders .= "$lowerKey:$value\n";
            $signedHeadersList[] = $lowerKey;
        }
        $signedHeaders = join(';', $signedHeadersList);

        // 6. 构造 Canonical Request
        //   Method + "\n" +
        //   URI + "\n" +
        //   QueryString + "\n" +
        //   CanonicalHeaders + "\n" +
        //   SignedHeaders + "\n" +
        //   HexEncode(Hash(Payload))

        $canonicalRequest = implode("\n",[
            'POST',
            '/',
            $queryString,
            $canonicalHeaders,
            '',
            $signedHeaders,
            $xContentSha256,
        ]);
        // 7. 构造 StringToSign
        $hashedCanonicalRequest = hash("sha256", $canonicalRequest);
        $credentialScope = join('/', [$shortDate, $region, $service, 'request']);
        $stringToSign = join("\n", ['HMAC-SHA256', $xDate, $credentialScope, $hashedCanonicalRequest]);


        // 8. 计算 Signing Key（注意：第一层密钥 = "volcengine" + SecretKey）
        $kDate = hash_hmac('sha256', $shortDate,   $secretAccessKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'request', $kService, true);

        // 9. 计算最终签名
        //$signature = bin2hex(hash_hmac('sha256', $stringToSign, $kSigning, true));
        $signature = hash_hmac("sha256", $stringToSign, $kSigning);

        // 10. 构造 Authorization 头
        $signResult['Authorization'] = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s",
            $accessKeyId . '/' . $credentialScope,
            $signedHeaders,
            $signature
        );
        $header = array_merge($headers, $signResult);
        return $header;
    }

}
