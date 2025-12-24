<?php
namespace Note\Service;

/**
 * 笔记 service
 * $Id: note.php $
 * @author mengrui
 */
class Note
{
    const NOTE_TYPE_AUDIO   = 1;
    const NOTE_TYPE_TEXT    = 2;
    const NOTE_TYPE_IMAGE   = 3;
    const NOTE_MODERATION_STATUS = 2;
    /**
     * @var mixed
     */
    private $_daoVnNoteModel;
    private $_noteAiAnalysisService;
    private $_daoVnAiPromptTemplatesModel;
    private $_daoVnAiAnalysisTypesModel;
    private $_daoVnNoteTagsModel;

    /**
     * 构造函数
     * @param  void
     * @throws \Exception
     * @return void
     */
    public function __construct()
    {
        $this->_daoVnNoteModel  = \Lsf\Loader::model('DaoVnNotes', false, APP_NAME_NOTE);
        $this->_noteAiAnalysisService = \Lsf\Loader::service('NoteAiAnalysis', false, APP_NAME_NOTE);
        $this->_daoVnAiPromptTemplatesModel = \Lsf\Loader::model('DaoVnAiPromptTemplates', false, APP_NAME_NOTE);
        $this->_daoVnAiAnalysisTypesModel = \Lsf\Loader::model('DaoVnAiAnalysisTypes', false, APP_NAME_NOTE);
        $this->_daoVnNoteTagsModel = \Lsf\Loader::model('DaoVnNoteTags', false, APP_NAME_NOTE);
    }

    /**
     * 根据noteId获取笔记内容
     * @param  int $uid
     * @param  int $noteId
     * @throws \Exception
     * @return void
     */
    public function getNoteData($uid, $noteId){
        $where = [
            'user_id' => $uid,
            'id' => $noteId,
        ];
        $result = $this->_daoVnNoteModel->select('content', $where);

        if ($result === false) {
            //查询失败
            return -7;
        } else {
            return $result;
        }
    }

    /**
     * 根据noteId获取笔记详情
     * @param   string  $columns
     * @param   int     $uid
     * @param   int     $noteId
     * @return  void
     */
    public function getNoteInfo($columns = '*', $uid, $noteId){
        $where = [
            'user_id' => $uid,
            'id' => $noteId,
            'moderation_status' => self::NOTE_MODERATION_STATUS,
        ];
        $result = $this->_daoVnNoteModel->select($columns, $where);

        if ($result === false) {
            //查询失败
            return -7;
        }
        $tags = $this->_daoVnNoteTagsModel->select('id,name' , ['note_id' => $noteId]);
        if ($tags === false) {
            //查询失败
            return -7;
        }
        if(empty($tags)){
            $noteInfo['tags'] = [];
        }else{
            $noteInfo['tags'] = $tags;
        }

        if(isset($result[0]) && !empty($result[0])){
            foreach($result[0] as $k => $v){
                $noteInfo[$k] = $v;
            }
        }
        return $noteInfo;
    }

    /**
     * 根据AI分析提示词
     * @param  string   $text
     * @param  string   $promptKey
     * @param  string   $scene
     * @throws \Exception
     * @return void
     */
    public function getNotePrompt($text, $promptKey = 'diary_analysis', $scene = 'default'){
        $where = [
            'prompt_key' => $promptKey,
            'scene' => $scene,
            'version' => 'v1',
        ];
        $result = $this->_daoVnAiPromptTemplatesModel->select ('system_prompt,user_prompt_template,variables' , $where);
        if ($result === false) {
            //查询失败
            return -7;
        }
        if(isset($result[0]) && !empty($result[0])){
            $promptRow = [
                'system_prompt' => $result[0]['system_prompt'],
                'user_prompt_template' => $result[0]['user_prompt_template'],
                'variables' => json_decode($result[0]['variables'], true),
            ];
        }else{
            return -6;
        }



        $AnalyseType = $this->_daoVnAiAnalysisTypesModel->select('name , json_schema',['is_active' => 1]);
        if ($AnalyseType === false) {
            //查询失败
            return -7;
        }

        $runtimeVars = [];
        foreach ($AnalyseType as $item) {
            if($item['name'] == 'insight'){
                $runtimeVars["{$item['name']}_schema"] = $item['json_schema'];
            }
        }
        if(!empty($runtimeVars)){
            $runtimeVars['diary_text'] = $text;
        }
        foreach ($promptRow['variables'] as $varName => $type) {
            if (!array_key_exists($varName, $runtimeVars)) {
                //log
                return false;
                //throw new RuntimeException("Missing prompt variable: {$varName}");
            }
        }

        $replaceMap = [];

        foreach ($promptRow['variables'] as $varName => $type) {
            $value = $runtimeVars[$varName];

            if ($type === 'json') {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $value = (string) $value;
            }

            $replaceMap['{{' . $varName . '}}'] = $value;
        }
        // 占位符替换
        $userPrompt = strtr(
            $promptRow['user_prompt_template'],
            $replaceMap
        );

        $messages = [
            [
                'role' => 'system',
                'content' => $promptRow['system_prompt'],
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ];
        return $messages;
    }

    /**
     * 添加笔记
     * @param   int     $uid
     * @param   string  $noteText
     * @param   array  $audioUrls
     * @return void
     */
    public function addAudioNote($uid, $noteText, $audioUrls)
    {
        $data = [
            'user_id' => $uid,
            'note_type' => self::NOTE_TYPE_AUDIO,
            'content' => $noteText,
            'media_url' => json_encode($audioUrls),
            'created_at' => date("Y-m-d H:i:s"),
        ];

        $result = $this->_daoVnNoteModel->insert($data);

        if ($result === false) {
            //入库失败
            return -7;
        } else {
            return $result;
        }
    }

    /**
     * 更新笔记记录（AI分析数据）
     * @param   int     $noteId
     * @param   string  $title
     * @param   string  $summary
     * @param   string  $analyzed_at
     * @param   int     $moderation_status
     * @return void
     */
    public function updateNoteAiData($noteId, $title, $summary, $analyzed_at, $moderation_status){

        $data = [
            'title' => $title,
            'summary' => $summary,
            'analyzed_at' => $analyzed_at,
            'moderation_status' => $moderation_status,
            'is_analyzed' => 1,
        ];
        $where = [
            'id' => $noteId,
        ];
        $result = $this->_daoVnNoteModel->update($data, $where);

        if ($result === false) {
            //操作失败
            return -6;
        } else {
            return $result;
        }
    }

    /**
     * 统一处理AI分析结果
     * @param int $noteId
     * @param string $title
     * @param string $summary
     * @param string $analyzedAt
     * @param int $complianceStatus
     * @return mixed
     */
    public function saveNoteAiResult($noteId, $title, $summary, $analyzedAt, $complianceStatus)
    {
        // 更新笔记主表
        $result = $this->updateNoteAiData($noteId, $title, $summary, $analyzedAt, $complianceStatus);

        // 如果更新成功，记录到AI分析表
        if ($result !== -6 && $result !== false) {
            $this->_noteAiAnalysisService->addNoteAiAnalysis($noteId, $analyzedAt);
        }

        return $result;
    }

    /**
     * 游标翻页获取笔记列表（按 id 倒序）
     * - 游标为空：从最新开始
     * - 游标不空：取 id < cursor 的下一页
     * - 返回 list 与 pagination（是否有下一页、下一页游标）
     * @param int         $uid       用户ID
     * @param int|null    $cursor    当前页游标（上一页最后一条的 id）
     * @param int         $pageSize  每页条数
     * @param array       $filters   额外过滤条件
     * @return array{list: array, pagination: array{has_next_page: bool, next_cursor: int|null}}
     */
    public function getNoteListByCursor($uid, $cursor = null, $pageSize = 20, $filters = [])
    {
        $where = array_merge(['user_id' => $uid , 'is_deleted' => 0], $filters);
        if (!empty($cursor)) {
            $where['id'] = ['LT', (int)$cursor];
        }
        $columns = 'id,title,summary,note_type,media_url,is_analyzed,analyzed_at,created_at';
        $orderBy = 'id DESC';
        $list = $this->_daoVnNoteModel->select($columns, $where, $orderBy, $pageSize + 1);
        if ($list === false) {
            return ['list' => [], 'pagination' => ['has_next_page' => false, 'next_cursor' => null]];
        }
        $hasNext = count($list) > $pageSize;
        if ($hasNext) {
            $list = array_slice($list, 0, $pageSize);
        }
        /*if (!empty($list)) {
            $noteIds = array_column($list, 'id');
            $tagsMap = $this->_getTagsByNoteIds($noteIds);
            foreach ($list as &$note) {
                $note['tags'] = isset($tagsMap[$note['id']]) ? $tagsMap[$note['id']] : [];
            }
            unset($note);
        }*/
        $nextCursor = $hasNext ? end($list)['id'] : null;
        return [
            'list' => $list,
            'pagination' => [
                'has_next_page' => $hasNext,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    /**
     * 软删除笔记及其关联数据
     * @param int $uid 用户ID
     * @param int $noteId 笔记ID
     * @return int 成功返回影响的行数，失败返回-6
     */
    public function softDeleteNoteAndActions($uid, $noteId)
    {
        // 软删除笔记主表
        $data = [
            'is_deleted' => 1,
            'deleted_at' => date("Y-m-d H:i:s"),
        ];
        $where = [
            'id' => $noteId,
            'user_id' => $uid,
        ];
        $result = $this->_daoVnNoteModel->update($data, $where);
        if ($result === false) {
            return -6;
        }

        // 软删除关联的AI分析记录
        $this->_noteAiAnalysisService->softDeleteByNoteId($noteId);

        // 软删除关联的标签记录
        /*$tagData = [
            'is_deleted' => 1,
            'deleted_at' => date("Y-m-d H:i:s"),
        ];
        $tagWhere = [
            'note_id' => $noteId,
        ];
        $this->_daoVnNoteTagsModel->update($tagData, $tagWhere);
*/
        return $result;
    }

    /**
     * 更新笔记字段（文本或摘要）
     * @param int    $uid     用户ID
     * @param int    $noteId  笔记ID
     * @param string $content 新的笔记内容（可选）
     * @param string $summary 新的摘要内容（可选）
     * @return int 成功返回影响的行数，失败返回-6
     */
    public function updateNoteFields($uid, $noteId, $content = '', $summary = '')
    {
        $data = [];
        if (!empty($content)) {
            $data['content'] = $content;
        }
        if (!empty($summary)) {
            $data['summary'] = $summary;
        }

        $where = [
            'id'     => $noteId,
            'user_id'=> $uid,
        ];

        $result = $this->_daoVnNoteModel->update($data, $where);
        return $result === false ? -6 : $result;
    }

}

