<?php
namespace Note\Controller;

/**
 * 笔记控制器
 * $Id: notes.php $
 * @author mengrui
 */

class Notes extends \App\Application
{
    /**
     * @var mixed
     */
    private $_asrService;
    private $_uploadService;
    private $_doubaoSummarizerService;
    private $_noteService;

    /**
     * 构造函数
     * @param  string $appName
     * @param  string $controllerName
     * @param  string $actionName
     * @return void
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);
        $this->_asrService = \Lsf\Loader::service('Asr', false, APP_NAME_NOTE);
        $this->_doubaoSummarizerService = \Lsf\Loader::service('DoubaoSummarizer', false, APP_NAME_NOTE);
        $this->_noteService = \Lsf\Loader::service('Note', false, APP_NAME_NOTE);
        $this->_uploadService = \Lsf\Loader::service('Upload', true);
    }

    /**
     * 语音笔记上传
     * @param  void
     * @return void
     */
    public function addAudio()
    {
        $this->uid = 101;
        $text = 'I’ve finally decided on a 5-day trip to Kyoto this autumn! The goal is relaxation, culture, and disconnecting from screens. I’ll fly into Kansai Airport on October 18th and stay in a traditional machiya townhouse near Gion.

Key priorities:

Visit Fushimi Inari at sunrise (avoid crowds!)
Book a tea ceremony experience in Arashiyama
Explore Nishiki Market for local snacks like matcha mochi and yuba
Take a day trip to Nara to see the deer park and Todai-ji Temple
I’ll keep my itinerary flexible—no rushing between spots. Packing light: comfortable walking shoes, a light jacket for cool mornings, and my sketchbook for quiet moments at temples.

Budget-wise, I’ve set ¥80,000 (~$550) for lodging, food, transport, and small souvenirs. Using a prepaid IC card (ICOCA) for trains/buses to simplify transit.

Most importantly: no work emails, minimal social media. Just wandering, observing, and soaking in the autumn colors. If plans change? That’s okay—spontaneity is part of the joy. Can’t wait to recharge!';
        $result = $this->_noteService->addAudioNote($this->uid,$text,$audioUrl = 'https://dashscope.oss-cn-beijing.aliyuncs.com/samples/audio/paraformer/hello_world_female2.wav');
var_dump($result);exit();
        $result = $this->_doubaoSummarizerService->summarize($text);
        var_dump($result);exit();
//        $url ='https://dashscope.oss-cn-beijing.aliyuncs.com/samples/audio/paraformer/hello_world_female2.wav';
//
//        //1. 识别
//        $result = $this->_asrService->voiceAsr($url , 'wav');

        exit();

        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $audio_info = $this->files('audio', true);

        //文件是否存在
        if (empty($audio_info) || !isset($audio_info['tmp_name']) || empty($audio_info['size'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'audio');
        }

        //上传错误
        /*if ($audio_info['error'] !== UPLOAD_ERR_OK  || $audio_info['size'] === 0) {
            throw new Exception("Invalid or empty audio");
        }*/
        // 验证音频有效性，获取音频时长
        $response = $this->_svrAudio->validateAudio($audio_info);

        /*if (is_int($response) && $response < 0) {
            switch ($response) {
                case -1://音频文件大小超过限制
                    $eCode = 9043020;
                    break;
                case -2:
                case -3: //类型错误
                    $eCode = 9043034;
                    break;
                case -4: //文件不是有效音频 //无法解析音频时长
                    $eCode = 9043035;
                    break;
                case -5: //无法解析音频时长 //音频时长超出限制
                    $eCode = 9043035;
                    break;
                case -6: //音频时长超出限制 //音频解析失败
                    $eCode = 9043035;
                    break;
                case -7: //音频解析失败
                    $eCode = 9043035;
                    break;
                // 未知错误
                default:
                    $eCode = $this->erroneous($response);
                    break;
            }
        } else {
            $result = isset($response['duration']) ?? '';
        }*/

        //上传音频
        if(is_array($response)&& isset($response['duration'])){
            //上传OSS
            $url = $this->_uploadService->uploadFileOss($uid, $scene = 'audio', $audio_info);

            var_dump($url);

            //AI解析

        }

        //如果text存在则不需要语音识别

        $noteText = $this->post('text', true);

        if (!isset($noteText) || empty($noteText) ) {
            //如果 text 为空 → 触发服务端 ASR
            $result = $this->_asrService->voiceAsr($url , 'wav');
            //$this->_asrService->execute($url);
            $noteText = $result['text'] ?? '';
        }

        //优先存储用户日记信息
        $this->_noteService->addAudioNote($this->uid,$noteText,$audioUrl);

        //需要验证用户是否有AI分析权限
        //识别tile & summary
        if($this->uid){// hasAI
            $result = $this->_doubaoSummarizerService->summarize($noteText);
        }else{
            $result = [];
        }

        //存储SQL

        $response = [
            'title' => $result['tilte'] ?? '',
            'summary' => $result['summary'] ?? '',
            ];
        return $this->json(ECODE_SUCCESS, $response);
    }
}
