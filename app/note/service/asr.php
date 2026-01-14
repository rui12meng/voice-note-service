<?php
namespace Note\Service;

/**
 * 语音识别服务
 * @author mengrui
 * $Id: asr.php $
 */

class Asr
{
    private $_svrVolcModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_svrVolcModel = \Lsf\Loader::Model('SvrVolc', false, APP_NAME_NOTE);
    }

    /**
     * 执行完整 ASR 识别流程：submit + 轮询 query
     * @param string $fileUrl 音频文件公网 URL（必须 HTTPS）[sign 带签名，有效性]
     //* @param int $maxWaitSeconds 最大等待时间（秒），默认 300
     * @return array 解析后的 ASR 结果
     * @throws Exception
     */
    public function voiceAsr(string $fileUrl, $platfrom = 'volc'): array
    {
        //获取url 扩展名
        $ext = pathinfo($fileUrl, PATHINFO_EXTENSION);

        $result = false;
        switch ($platfrom){
            case 'volc':
                $result = $this->asr_volc($fileUrl , $ext);
                break;
            default:
                break;
        }
        if($result === false){
            return -1;
        }
        return $result;
    }

    protected function asr_volc($fileUrl , $fileFormat){
        $startTime = microtime(true);
        // Step 1: Submit 任务
        $requestUuid = $this->generateUuid();

        $logId = $this->_svrVolcModel->asrSubmit($fileUrl, $fileFormat, $requestUuid);
        \Lsf\Loader::plugin('Log')->info('', [
            'call_function' => '[VOLC ASR] Task submitted',
            'reqID' => $requestUuid,
            '$fileUrl' => $fileUrl,
            'logID' => $logId,
        ]);

        // Step 2: 轮询查询结果
        $result = $this->_svrVolcModel->query($requestUuid, $maxWaitSeconds = 10);

        $durationMs = round((microtime(true) - $startTime) * 1000);
        \Lsf\Loader::plugin('Log')->info('', [
            'call_function' => '[VOLC ASR] Task query',
            'reqID' => $requestUuid,
            '$fileUrl' => $fileUrl,
            'result' => $result,
            'totalDuration' => $durationMs,
        ]);
        return $result;
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

}