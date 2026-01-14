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
    private $_douBaoSummarizerService;
    private $_noteService;
    private $_audioService;

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
        $this->_douBaoSummarizerService = \Lsf\Loader::service('DouBaoSummarizer', false, APP_NAME_NOTE);
        $this->_noteService = \Lsf\Loader::service('Note', false, APP_NAME_NOTE);
        $this->_audioService = \Lsf\Loader::service('Audio', false, APP_NAME_NOTE);
        $this->_uploadService = \Lsf\Loader::service('Upload', true);
    }

    /**
     * 语音笔记上传
     * @param  void
     * @return void
     */
    public function addAudio()
    {

        //$r = $this->_noteService->saveNoteAiResult($noteId, $result['title'], $result['summary'], $result['analyzed_at'], $result['compliance_status']);

        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $audioInfo = $this->files('audio', true);

        //文件是否存在
        if (empty($audioInfo) || !isset($audioInfo['tmp_name']) || empty($audioInfo['size'])) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'audio');
        }

        //文件无效
        if ((int)$audioInfo['error'] !== UPLOAD_ERR_OK  || (int)$audioInfo['size'] === 0) {
            return $this->json(1003000, [], 'Invalid or empty audio');
        }

        // 验证音频有效性，获取音频时长
        $result = $this->_audioService->validateAudio($audioInfo);

        if (is_int($result) && $result < 0) {
            switch ($result) {
                case -1://音频文件大小超过限制
                    $eCode = 1003001;
                    break;
                case -2:
                case -3: //类型错误
                    $eCode = 1003002;
                    break;
                case -6: //音频时长超出限制 //音频解析失败
                    $eCode = 1003004;
                    break;
                // 未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
                    break;
            }
            return $this->json($eCode, []);
        }
        $duration = isset($result['duration']) ? (int)$result['duration'] : 0;
        //上传音频
        $pathUrl = '';
        $signUrl = '';
        if($duration > 0){
            //上传OSS，获取文件相对路径
            $result = $this->_audioService->uploadAudio($audioInfo, $scene = 'audio');
            if($result === false){ //上传失败
                return $this->json(1003005, []);
            }
            $pathUrl = isset($result['pathUrl']) ? $result['pathUrl'] : '';
            $signUrl = isset($result['signUrl']) ? $result['signUrl'] : '';
        }

        //如果text存在则不需要语音识别 todo 限制1000字符
        $noteText = $this->post('text', true);

        if (!isset($noteText) || empty($noteText) ) {
            //如果 text 为空 → 触发服务端 ASR
            if(!empty($signUrl)){
                $result = $this->_asrService->voiceAsr($signUrl); //扩展名放到voiceAsr内部处理
                if(is_int($result) && $result < 0){// 语音识别失败
                    return $this->json(1003006, []);
                }
                $noteText = $result['text'] ?? '';
            }
        }
        if (isset($noteText) && mb_strlen($noteText) > 1000) {
            $noteText = mb_substr($noteText, 0, 1000);
        }

        //优先存储用户日记信息
        $noteId = $this->_noteService->addAudioNote($uid, $noteText, array($pathUrl));
        $eCode = ECODE_SUCCESS;
        $response = [];
        if(is_int($noteId) && $noteId < 0){
            switch ($noteId){
                // 数据库操作失败
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                // 未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }else{
            $response = [
                'note_id' => $noteId,
                'note_type' => 'audio',
                'title' => '语音日记',
                'tags' => [],
                'audio_urls' => array($signUrl),
                'content' => $noteText,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        //todo 【关键】启动后台任务（不阻塞当前协程）
        if (!empty($noteText) && is_string($noteText) && mb_strlen(trim($noteText), 'UTF-8') > 100) {
            go(function () use ($uid, $noteId, $noteText) {
                $this->_noteService->doAnalyzeNotesTasks($uid, $noteId, $noteText);
            });
        }


        return $this->json($eCode, $response);
    }


    /**
     * 笔记分析
     * @param  void
     * @return void
     */
    public function noteAnalysis(){

        $text = 'Today was one of those golden days I’ll tuck away in my heart forever. It started early—6:30 a.m.—with my six-year-old, Lily, shaking my shoulder, whispering, \'Mom, the sun’s up! Can we go to the park like you promised?\' Her eyes sparkled with that mix of sleepiness and excitement only kids possess. I said yes before my brain fully caught up, and by 8 a.m., we were at Meadowbrook Park, picnic basket in hand, dew still clinging to the grass.\n\nWe didn’t have a plan, just time. We fed ducks (Lily insisted on naming each one—Quackers, Flufftail, Sir Waddles), skipped stones across the pond (she beat me 7–2!), and built a lopsided sandcastle that she declared \'the palace of Queen Lily the Brave.\' Around noon, we spread our blanket under an oak tree and shared peanut butter sandwiches and apple slices. She told me about her dream last night—flying on a dragon made of rainbows—and I realized how rarely I truly listen without checking my phone or mentally drafting emails.\n\nAfter lunch, we joined a free nature walk led by a park ranger. Lily asked endless questions: \'Why do squirrels bury nuts?\' \'Do trees get lonely?\' The ranger smiled and said, \'You’ve got the curiosity of a scientist!\' Her pride was palpable. On the way home, she fell asleep in the car, cheek smudged with dirt, hair tangled with leaves. I carried her inside, her weight familiar and fleeting.\n\nTonight, as I washed paint-stained clothes (we’d stopped at the community art tent for finger-painting), I felt a deep calm. No screens, no schedules—just presence. I remembered how she hugged me tight after finding a four-leaf clover: \'This is for you, Mommy, because you’re my lucky day.\' In a world of deadlines and distractions, today reminded me that joy lives in the small, unplanned moments. I resolved to protect these pockets of slowness. Childhood isn’t waiting for \'someday\'; it’s happening now, in sticky fingers and whispered secrets. Tomorrow, I’ll say \'yes\' again—even if it’s raining.';
        $result = $this->_noteService->doAnalyzeNotesTasks($uid=1, $noteId=1,$text);
        return $this->json(0,$result);
        /*$uid = $this->uid;
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
        }*/

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
    public function noteList(){
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
        if ( (!isset($text) || empty($text)) && (!isset($summary) || empty($summary)) ) {
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

