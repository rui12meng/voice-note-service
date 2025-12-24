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
        $noteId = $this->_noteService->addAudioNote($this->uid,$text,$audioUrl = 'https://dashscope.oss-cn-beijing.aliyuncs.com/samples/audio/paraformer/hello_world_female2.wav');

        $result = $this->_doubaoSummarizerService->summarize($text, $this->uid, $noteId);


        $r = $this->_noteService->saveNoteAiResult($noteId, $result['title'], $result['summary'], $result['analyzed_at'], $result['compliance_status']);
        var_dump( [$noteId, $result , $r]);exit();

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

    /**
     * 笔记分析
     * @param  void
     * @return void
     */
    public function noteAnalysis(){
        $uid = $this->uid;
        //优先判断用户是否有权限

        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        // 获取笔记内容
        $result = $this->_noteService->getNoteData($uid, $noteId);
        $text = $result[0]['content'] ?? "";
        if(!empty($text)){
            //分析
            $promptMessage = $this->_noteService->getNotePrompt($text);

            $this->_doubaoSummarizerService->aiAnalysis($promptMessage, $this->uid, $noteId);
        }else{
            return [];
        }

    }

    /**
     * 用户获取日记详情
     * @param  void
     * @return void
     */
    public function info(){
        /*$uid = $this->uid;
        //优先判断用户是否有权限

        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }*/
        $uid = 101;
        $noteId = 7;
        $columns = 'id,title,summary,content,note_type,media_url,is_analyzed,created_at';
        $result = $this->_noteService->getNoteInfo($columns, $uid, $noteId);

        $eCode  = ECODE_SUCCESS;
        $returnData = [];
        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                //数据不存在
                case -6:
                    $eCode = ECODE_DATA_NOT_FOUND;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        } else {
            $returnData['note_id'] = $result['id'];
            $returnData['note_type'] = $result['note_type'];
            $returnData['title'] = $result['title'];
            $returnData['tags'] = $result['tags'];
            $returnData['media_url'] = $result['media_url'];
            $returnData['status'] = $result['is_analyzed']; //0=未分析，1=已分析
            $returnData['content'] = $result['content'];
            $returnData['summary'] = $result['summary'];
            $returnData['created_at'] = $result['created_at'];
        }

        return $this->json($eCode, $returnData);

    }

    /**
     * 用户获取日记列表
     * @param  void
     * @return void
     */
    public function list(){
        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $cursor = $this->post('cursor', true);
        if ( ! isset($cursor) || empty($cursor) || $cursor < 0) {
            //游标（Base64 编码的 (created_at, id)）
            //$cursor = 1;
        }
        $pageSize = $this->post('limit', true);
        if ( ! isset($pageSize) || empty($pageSize) || $pageSize < 0) {
            $pageSize = 20;
        }
        // 调用服务层获取列表
        $result = $this->_noteService->getNoteListByCursor($uid, $cursor, $pageSize);
        if (is_int($result) && $result < 0) {
            // 服务层返回错误码
            return $this->json(ECODE_DATABASE_QUERY_FAIL, []);
        }

        return $this->json(ECODE_SUCCESS, $result);
    }

    /**
     * 删除笔记（软删除）
     * @param  void
     * @return void
     */
    public function delete()
    {
//        $uid = $this->uid;
//        if (!isset($uid) || empty($uid)) {
//            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
//        }

        $uid = 101;
        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        // 调用服务层执行软删除笔记及关联行动项
        $result = $this->_noteService->softDeleteNoteAndActions($uid, $noteId);

        if (is_int($result) && $result < 0) {
            switch ($result) {
                case -6: // 数据不存在
                    $eCode = ECODE_DATA_NOT_FOUND;
                    break;
                case -7: // 数据库异常
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
            return $this->json($eCode, []);
        }

        return $this->json(ECODE_SUCCESS, []);
    }
    /**
     * 修改日记（仅支持文本与摘要）
     * @return void
     */
    public function edit()
    {
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        // 客户端可传 text / summary 之一或两者
        $text   = $this->post('text', true);      // 日记正文
        $summary = $this->post('summary', true);  // 摘要

        // 至少传一个字段
        if (!isset($text) || empty($text) || !isset(summary) || empry(summary)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'text or summary');
        }

        // 调用服务层更新
        $result = $this->_noteService->updateNoteFields($uid, $noteId, $text, $summary);

        if (is_int($result) && $result < 0) {
            switch ($result) {
                case -6: // 笔记不存在
                    $eCode = ECODE_DATA_NOT_FOUND;
                    break;
                case -7: // 数据库异常
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
            return $this->json($eCode, []);
        }

        return $this->json(ECODE_SUCCESS, []);
    }
}
}

