<?php
namespace Model;

use phpDocumentor\Reflection\Types\String_;

/**
 * 火山引擎服务
 * @author mengrui
 * $Id: svr_volc.php $
 */
class SvrVolc extends \Model\SvrBase
{
    private $_volcAsrConf;
    private $_appKey;
    private $_apiKey;
    private $_accessKey;
    private $_resourceId;


    public function __construct()
    {
        parent::__construct();
        $this->_volcAsrConf = \Lsf\Env::group('VOLC_');
        $this->_appKey = $this->_volcAsrConf['asr_app_key'];
        $this->_apiKey = $this->_volcAsrConf['text_api_key'];
        $this->_accessKey = $this->_volcAsrConf['asr_access_key'];
        $this->_resourceId = $this->_volcAsrConf['asr_resource_id'];
    }

    /**
     * 提交 ASR 任务
     * @param string $fileUrl
     * @param string $fileFormat 文件类型（扩展格式）
     * @return string $requestUuid（唯一请求id）
     * @throws Exception
     */
    public function asrSubmit(string $fileUrl, string $fileFormat, string $requestUuid)
    {
        $apiSign = 'volc_asr_submit';

        $payload = [
            'user' => [
                'uid' => 'php_swoole_' . substr(md5($requestUuid), 0, 16),
            ],
            'audio' => [
                'format' => $fileFormat,
                'url'    => $fileUrl,
            ],
            "request" => [
                'model_name' => 'bigmodel',
                'model_version' => '400',
                'show_utterances' => false,
            ],
        ];

        $headers = $this->buildAuthHeaders($requestUuid);

        $response = $this->post($apiSign, $payload, $headers);

        // 检查业务状态码
        $statusCode = $response['headers']['x-api-status-code'] ?? '';
        $message    = $response['headers']['x-api-message'] ?? '';
        $logId      = $response['headers']['x-tt-logid'] ?? '';

        if ($statusCode !== '20000000') {
            \Lsf\Loader::plugin('Log')->error(1003507, [
                'error' => 'volc asr Submit API error',
                'code' => $statusCode,
                'message' => $message,
                ]);
            return -1;
        }

        if (empty($logId)) {
            return -2;
        }
        return $logId;
    }

    /**
     * 单次查询任务状态
     *
     * @param string $reqId
     * @return array{body: string, headers: array}
     * @throws Exception
     */
    private function doQuery(string $reqId): array
    {
        $apiSign = 'volc_asr_query';

        $params = [];

        $headers = $this->buildAuthHeaders($reqId);

        $response = $this->post($apiSign, $params, $headers);
        return [
            'body'    => $response['body'],
            'headers' => $response['headers'],
        ];
    }

    /**
     * 循环查询直到任务完成
     *
     * @param string $reqId
     * @param int $maxWaitSeconds
     * @return array
     * @throws Exception
     */
    public function query(string $reqId, int $maxWaitSeconds)
    {
        $startTime = time();

        while (true) {
            // 协程 sleep（因已开启 SWOOLE_HOOK_SLEEP，sleep() 自动协程化）
            sleep(1);

            try {
                $result = $this->doQuery($reqId);
                $body    = $result['body'];
                $headers = $result['headers'];

                //var_dump($result);exit();
                $code    = $headers['x-api-status-code'] ?? '';
                $message = $headers['x-api-message'] ?? '';

                if ($code === '20000000') {
                    return $body;
                }

                // 可重试状态：20000001（处理中）、20000002（排队中）
                if (!in_array($code, ['20000001', '20000002'], true)) {
                    \Lsf\Loader::plugin('Log')->error(1003505, ['error' => "VOLC ASR task failed permanently: code={$code}, message={$message}"]);
                    return [];
                }

                // 超时检查
                if (time() - $startTime > $maxWaitSeconds) {
                    \Lsf\Loader::plugin('Log')->error(1003506, ['error' => "VOLC ASR task timeout after {$maxWaitSeconds} seconds"]);
                    return [];
                }

            } catch (\Exception $e) {
                \Lsf\Loader::plugin('Log')->error(1003507, ['error' => "VOLC ASR Query error:  {$e->getMessage()}"]);
                return [];
            }
        }
    }

    /**
     * 提交 AI分析（title/summary） 任务
     * @param array $request 任务内容
     * @return void
     * @throws Exception
     */
    public function aiSummarize(array $request){
        $apiSign = 'volc_chat_completions';

        $headers = $this->buildAuthTextHeaders();

        $response = $this->post($apiSign, $request, $headers, 2);

        return $response['body'] ?? '';
    }

    /**
     * 构造认证 Header
     * @param string $reqId
     * @return array
     */
    private function buildAuthTextHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->_apiKey}",
        ];
    }

    /**
     * 构造认证 Header
     * @param string $reqId
     * @return array
     */
    private function buildAuthHeaders(string $reqId): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-Api-App-Key' => $this->_appKey,
            'X-Api-Access-Key' => $this->_accessKey,
            'X-Api-Resource-Id' => $this->_resourceId,
            'X-Api-Request-Id' => $reqId,
            'X-Api-Sequence' => -1,
        ];
    }

}