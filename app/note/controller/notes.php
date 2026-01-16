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
     * 添加语音笔记
     * 上传OSS+ASR语音识别+AI分析全流程
     * @param  void
     * @return void
     */
    public function addAudio()
    {
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
                $noteText = isset($result['text']) ? $result['text'] : '';
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
    public function analysis(){

        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'uid');
        }
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        // 获取笔记内容
        $result = $this->_noteService->getNoteData($uid, $noteId);
        if(empty($result) || !isset($result[0])){
            return $this->json(ECODE_DATABASE_QUERY_FAIL , []);
        }
        $eCode = ECODE_SUCCESS;
        //todo 检验是否已经被分析过：如果未分析走分析逻辑；已分析直接返回分析结果
        if(isset($result[0]['is_analyzed']) && (int)$result[0]['is_analyzed'] === 0){

            $text = isset($result[0]['content']) ? $result[0]['content'] : "";
            if(empty($text) || mb_strlen(trim($text), 'UTF-8') < 100){
                return $this->json(1003008 , []);
            }
            $result = $this->_noteService->doAnalyzeNotesTasks($uid, $noteId, $text);

            if(is_int($result) && (int)$result < 0){
                switch ($result){
                    case -1:
                        $eCode = 1003009;
                        break;
                    case -2:
                        $eCode = 1003010;
                        break;
                    default:
                        $eCode = ECODE_UNDEFINED_ERROR;
                        break;
                }
                return $this->json($eCode, []);
            }
        }

        // todo 分析完毕后，聚合返回AI分析数据
        $aiData = $this->_noteService->getAiAnalyzedData($uid, $noteId);
        return $this->json($eCode, $aiData);

    }

    /**
     * 用户获取日记AI分析详情数据
     * todo 根据habit返回的下游如果包含habit id等信息说明已添加/若只返回内容，说明未添加
     * @param  void
     * @return void
     */
    public function analyzed(){
        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'uid');
        }
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        $result = $this->_noteService->getAiAnalyzedData($uid, $noteId);

        return $this->json( 0, $result);

    }

    /**
     * 用户获取日记详情
     * @param  void
     * @return void
     */
    public function info(){
        $uid = $this->uid;
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        $result = $this->_noteService->getInfoById($uid, $noteId);

        $eCode  = ECODE_SUCCESS;
        $returnData = [];
        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                case -6:
                    $eCode = ECODE_DATA_NOT_FOUND;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
                    break;
            }
        } else {
            $returnData['note_id'] = $result['id'];
            $returnData['note_type'] = $result['note_type'];
            $returnData['title'] = $result['title'];
            $returnData['tags'] = $result['tags'];
            $returnData['media_url'] = $result['media_url']; //todo 返回可访问地址
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
    public function lists(){
        $uid = $this->uid;
        if ( ! isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $cursor = $this->post('cursor', true);
        if ( ! isset($cursor) || empty($cursor) || $cursor < 0) {
            //游标（Base64 编码的 (created_at, id)）
            $cursor = 0;
        }
        $pageSize = $this->post('limit', true);
        if ( ! isset($pageSize) || empty($pageSize) || $pageSize < 0) {
            $pageSize = 20;
        }
        // todo 可按照标题搜索（标签搜索暂不支持） ---- shujubiao title tianjia FULLTEXT KEY
        $keyword = $this->post('keyword', true);
        if ( ! isset($keyword) || empty($keyword)) {
            $keyword = '';
        }
        // 调用服务层获取列表
        $result = $this->_noteService->getNoteListByCursor($uid, $cursor, $pageSize, [], $keyword);
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
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

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
     * @param  void
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
//                case -6: // 笔记不存在
//                    $eCode = ECODE_DATA_NOT_FOUND;
//                    break;
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

