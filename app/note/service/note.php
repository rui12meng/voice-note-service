<?php
namespace Note\Service;

/**
 * 笔记 service
 * $Id: note.php $
 * @author mengrui
 */
class Note extends \Service\Base
{
    const NOTE_TYPE_AUDIO   = 1;
    const NOTE_TYPE_TEXT    = 2;
    const NOTE_TYPE_IMAGE   = 3;
    const NOTE_MODERATION_STATUS = 2;

    const REDIS_EXPIRE_BASE_TIME = 3600;
    const REDIS_KEY_USER_LIMIT_FOR_ANALYZE_TEXT = 'voice-note-service:free-user-limit-for-analyze-text';
    const REDIS_KEY_PROMPT_FOR_ANALYZE_TEXT = 'voice-note-service:prompt-for-analyze-text';
    const REDIS_KEY_USER_LIMIT_FOR_ANALYZE_TEXT_MAX_TIMES = 2;

    /**
     * @var mixed
     */
    private $_noteAiAnalysisService;
    private $_douBaoSummarizerService;
    private $_daoVnNoteModel;
    private $_daoVnAiPromptTemplatesModel;
    private $_daoVnAiAnalysisTypesModel;
    private $_daoVnNoteTagsModel;
    private $_daoVnNoteAiAnalysisModel;
    private $_daoVnActionsModel;
    private $_daoVnHabitsModel;
    private $_daoVnEmotionTagsModel;
    private $_uploadService;

    /**
     * 构造函数
     * @param  void
     * @throws \Exception
     * @return void
     */
    public function __construct()
    {
        $this->_noteAiAnalysisService = \Lsf\Loader::service('NoteAiAnalysis', false, APP_NAME_NOTE);
        $this->_douBaoSummarizerService = \Lsf\Loader::service('DouBaoSummarizer', false, APP_NAME_NOTE);
        $this->_daoVnNoteModel  = \Lsf\Loader::model('DaoVnNotes', false, APP_NAME_NOTE);
        $this->_daoVnAiPromptTemplatesModel = \Lsf\Loader::model('DaoVnAiPromptTemplates', false, APP_NAME_NOTE);
        $this->_daoVnAiAnalysisTypesModel = \Lsf\Loader::model('DaoVnAiAnalysisTypes', false, APP_NAME_NOTE);
        $this->_daoVnNoteTagsModel = \Lsf\Loader::model('DaoVnNoteTags', false, APP_NAME_NOTE);
        $this->_daoVnNoteAiAnalysisModel = \Lsf\Loader::model('DaoVnNoteAiAnalysis', false, APP_NAME_NOTE);
        $this->_daoVnEmotionTagsModel = \Lsf\Loader::model('DaoVnEmotionTags',true);
        $this->_daoVnActionsModel = \Lsf\Loader::model('DaoVnActions', true);
        $this->_daoVnHabitsModel = \Lsf\Loader::model('DaoVnHabits', true);
        $this->_uploadService = \Lsf\Loader::service('Upload', true);
    }

    /**
     * 日记分析（身份验证/分析/存储）
     * @param int $uid
     * @param int $noteId
     * @param  string $content
     * @param string $entryType
     * @throws \Exception
     * @return void
     */
    public function doAnalyzeNotesTasks($uid, $noteId, $content, $entryType = 'Audio'){
        // todo 1. 验证用户AI分析权限：免费用户每天最多2次
        $redisKey = self::REDIS_KEY_USER_LIMIT_FOR_ANALYZE_TEXT . ':' . $entryType . ':' . date('Ymd') . ':' . $uid;
        $usedTimes = (int)\Lsf\Loader::plugin('RedisPool')->redis()->get($redisKey);

        if ($usedTimes >= self::REDIS_KEY_USER_LIMIT_FOR_ANALYZE_TEXT_MAX_TIMES) {
            // 超过当日次数限制
            \Lsf\Loader::plugin('Log')->error(1002013, [
                'uid' => $uid,
                'note_id' => $noteId,
                'error' => '今日AI分析次数已用完',
            ]);
            return -1;
        }

        //todo 2. 检查该noteId是否已经AI分析过
        $isAnalyzed = $this->checkIfAnalyzed($noteId);
        if($isAnalyzed === true){
            return -2;
        }

        //todo 3. 笔记AI分析
        $promptMessage = $this->getNotePrompt($content);
        $result = $this->_douBaoSummarizerService->aiAnalysis($promptMessage, $uid, $noteId);

        //todo 4. AI分析后更新表数据
        if(is_array($result) && !empty($result)){
            $this->storeAiData($uid, $noteId, $result);
        }

        //todo 更新缓存次数
        $this->incrUntilMidnight($redisKey);

        return $result;

    }

    /**
     * 获取用户免费分析次数
     * @param int $uid 用户唯一ID
     * @return void
     */
    public function getUserTodayFreeLimit($uid){
        $redisKey = self::REDIS_KEY_USER_LIMIT_FOR_ANALYZE_TEXT . ':' . 'Audio' . ':' . date('Ymd') . ':' . $uid;
        try{
            $usedTimes = (int)\Lsf\Loader::plugin('RedisPool')->redis()->get($redisKey);

            $response = [];
            $audioData = [];
            $audioData['quotaType'] = 'FREE';
            $audioData['freeLimit'] = self::REDIS_KEY_USER_LIMIT_FOR_ANALYZE_TEXT_MAX_TIMES;
            // todo Key 不存在
            if ($usedTimes === false) {
                $audioData['usedCount'] = 0;
            } else {// todo Key 存在
                $audioData['usedCount'] = $usedTimes;
            }
            if ($usedTimes >= self::REDIS_KEY_USER_LIMIT_FOR_ANALYZE_TEXT_MAX_TIMES) {
                // 超过当日次数限制
                $audioData['hasFreeQuota'] = false;
            }else{
                $audioData['hasFreeQuota'] = true;
            }
            $response['audio'] = $audioData;
            return $response;

        }catch(\RedisException $e){
            \Lsf\Loader::plugin('Log')->error(1000511, [
                'redis_key'     => $redisKey,
                'call_function' => 'get',
                'code'          => $e->getCode(),
                'message'       => $e->getMessage()
            ]);
            return FALSE;
        }

    }

    /**
     * 增加计数并设置过期时间为当天 24:00
     * @param string $key 键名 (建议带上日期前缀或业务前缀，如 "stat:visit:2023-10-27")
     * @return int 增加后的新值
     */
    private function incrUntilMidnight($key) {
        $redis = \Lsf\Loader::plugin('RedisPool')->redis();
        // 第一步：原子性加 1
        $newValue = $redis->incr($key);

        // 第二步：如果返回值为 1，说明是今天第一次创建该 Key，需要设置过期时间
        if ($newValue === 1) {
            // 计算当前时间到当天 24:00 (即明天 00:00) 的剩余秒数
            $now = time();

            // 获取明天的零点时间戳
            $nextMidnight = strtotime('tomorrow');

            // 计算剩余秒数
            $ttl = $nextMidnight - $now;

            // 防御性编程：防止计算出错导致 ttl <= 0
            if ($ttl > 0) {
                $redis->expire($key, $ttl);
            } else {
                // 极端情况：如果在 23:59:59 执行，可能 ttl 为 0 或负数，
                // 此时可以直接设一个很小的值，或者设为 1 秒让它立刻过期
                $redis->expire($key, 1);
            }
        }

        return $newValue;
    }

    /**
     * 存储笔记AI分析数据
     * @param   int     $uid
     * @param   int     $noteId
     * @param   array   $data
     * @return void
     */
    public function storeAiData($uid, $noteId, $data){

        $insight = isset($data['insight']) ? $data['insight'] : [];
        $emotion = isset($data['mood_analysis']) ? $data['mood_analysis'] : [];
        $actions = isset($data['actions']) ? $data['actions'] : [];
        $habits = isset($data['habits']) ? $data['habits'] : [];

        $items = [
            'insight' => is_array($insight) ? $insight : (string)$insight,
            'emotion' => is_array($emotion) ? $emotion : (string)$emotion,
            'actions' => is_array($actions) ? $actions : (string)$actions,
            'habits' => is_array($habits) ? $habits : (string)$habits,
        ];
        $this->_noteAiAnalysisService->addBatchNoteAiAnalysis($noteId, $data['ai_model'], $items);

        //todo 同时更新notes表is_analyzed 为分析状态
        $actionMap = [
            'allow' => 1, // 正常
            'warn'  => 2, // 警告
            'block' => 3  // 拦截
        ];

        $uData = [
            'title' => $data['title'],
            'summary' => $data['summary'],
            'ai_model_version' => $data['ai_model'],
            'is_critical' => $data['is_critical'] === true ? 1 : 0,
            'moderation_status' => $actionMap[$data['action']] ?? 1,
            'is_analyzed' => 1,
            'analyzed_at' => date('Y-m-d H:i:s'),
        ];
        $this->_daoVnNoteModel->update($uData, ['id'=>$noteId]);

        //todo 存储tags到tag表，批量存储
        $dataTags = [];
        foreach ($data['tags'] as $k => $v) {
            if(!empty($v)){
                $dataTags[] = [
                    'note_id' => $noteId,
                    'user_id' => $uid,
                    'name' => $v,
                    'normalized_name' => strtolower(trim($v)),
                    'source' => 'ai',
                ];
            }
        }
        if(!empty($dataTags)){
            $this->_daoVnNoteTagsModel->batchInsert($dataTags);
        }

        //todo 存储emotionTags到情绪表
        $mood = isset($emotion['emotion']) ? $emotion['emotion'] : '';
        $intensity = isset($emotion['intensity']) ? $emotion['intensity'] : 0;

        if(isset($mood) && !empty($mood)){
            $EmotionData = [
                'note_id' => $noteId,
                'emotion_type_id' => emotionMap($mood , 'value_to_key'),
                'intensity' => $intensity,
            ];
            $this->_daoVnEmotionTagsModel->insert($EmotionData);
        }

        //todo 存储actions到用户行动表
        $dataActions = [];
        if(!empty($actions)){
            foreach ($actions as $k => $v) {
                if(!empty($v['step'])){
                    $dataActions[] = [
                        'note_id' => $noteId,
                        'user_id' => $uid,
                        'title' => $v['step'],
                        'source' => 'ai',
                        'due_date' => $v['timing'] === '当下' ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime('tomorrow')),
                    ];
                }
            }
        }
        if(!empty($dataActions)){
            $this->_daoVnActionsModel->batchInsert($dataActions);
        }
    }

    /**
     * 获取笔记分析数据
     * @param   int     $uid
     * @param   int     $noteId
     * @return void
     */
    public function getAiAnalyzedData($uid, $noteId){

        $aiResult = $this->_daoVnNoteAiAnalysisModel->getAiDataBySql($noteId);

        $actions = $this->_daoVnActionsModel->getActions($uid, $noteId);

        //$habitsDb = $this->_daoVnHabitsModel->getHabitsByNoteId($uid, $noteId);

        $insight = [];
        $emotion = [];
        //$habitAi = [];

        if ($aiResult !== false && !empty($aiResult)) {
            foreach ($aiResult as $row) {
                if (!isset($row['analysis_type_name'], $row['analysis_data'])) {
                    continue;
                }
                $type = $row['analysis_type_name'];
                $rawData = $row['analysis_data'];

                $value = [];
                if (is_string($rawData) && $rawData !== '') {
                    $decoded = json_decode($rawData, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $value = $decoded;
                    } else {
                        $value = [$rawData];
                    }
                }

                switch ($type){
                    case 'insight':
                        $insight = $value;
                        break;
                    case 'emotion':
                        $emotion = $value;
                        break;
//                    case 'habits':
//                        $habitAi = $value;
//                        break;
                    default:
                        break;
                }
            }
        }

        if (!is_array($actions)) {
            $actions = [];
        }

//        if (is_array($habitsDb) && !empty($habitsDb)) {
//            $habits = isset($habitsDb[0]) ? $habitsDb[0] : [];
//        } else {
//            if (is_array($habitAi) && !empty($habitAi)) {
//                if (isset($habitAi['habit_suggestion'])) {
//                    $habitAi['habit_name'] = $habitAi['habit_suggestion'];
//                    unset($habitAi['habit_suggestion']);
//                }
//            }
//            $habits = $habitAi;
//        }

//        if (!is_array($habits)) {
//            $habits = [];
//        }

        return [
            'insight' => (object)$insight,
            'emotion' => (object)$emotion,
            'actions' => $actions,
            //'habits' => (object)$habits,
        ];
    }

    /**
     * 根据noteId是否已经被分析
     * @param   int     $noteId
     * @return bool  true 表示已分析，false 表示未分析、记录不存在或查询失败
     */
    private function checkIfAnalyzed(int $noteId){
        if ($noteId <= 0) {
            return false;
        }

        $columns = 'is_analyzed';
        $result = $this->_daoVnNoteModel->find($columns, $noteId);

        // 查询失败（返回 false）或结果中无 is_analyzed 字段，视为未分析
        if ($result === false || !isset($result['is_analyzed'])) {
            return false;
        }

        return (int)$result['is_analyzed'] === 1;
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
            'is_deleted' => 0,
        ];
        $result = $this->_daoVnNoteModel->select('content,is_analyzed', $where);

        if ($result === false) {
            //查询失败
            return [];
        } else {
            return $result;
        }
    }

    /**
     * 根据noteId获取笔记详情
     * @param   int     $uid
     * @param   int     $noteId
     * @return  void
     */
    public function getInfoById($uid, $noteId){

        $where = [
            'id' => $noteId,
            'user_id' => $uid,
            'is_deleted' => 0,
            //'moderation_status' => self::NOTE_MODERATION_STATUS, // 合规状态为正常
        ];
        $columns = 'id,title,summary,content,note_type,media_url,is_analyzed,created_at';
        $result = $this->_daoVnNoteModel->select($columns, $where);

        if ($result === false) {
            //查询失败
            return -7;
        }
        if(empty($result)){
            return -6;
        }

        $tags = $this->_daoVnNoteTagsModel->select('id,name' , ['user_id'=> $uid, 'note_id' => $noteId , 'is_deleted' => 0]);
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
                if ($k === 'media_url') {
                    $noteInfo[$k] = $this->buildSignedMediaUrls($v);
                } else {
                    $noteInfo[$k] = $v;
                }
            }
        }
        return $noteInfo;
    }

    /**
     * 根据noteId获取笔记tag
     * @param   int     $uid
     * @param   int     $noteId
     * @return  void
     */
    public function getNoteTag($uid, $noteId){
        $tags = $this->_daoVnNoteTagsModel->select('id,name' , ['user_id'=> $uid, 'note_id' => $noteId , 'is_deleted' => 0]);
        if ($tags === false) {
            //查询失败
            return -7;
        }
        $noteTags = [];
        if(is_array($tags) && !empty($tags)){
            $noteTags = $tags;
        }
        return $noteTags;
    }

    /**
     * 生成media_url签名地址
     * @param  string   $raw  media_url字段返回的json串
     * @return void
     */
    public function buildSignedMediaUrls($raw)
    {
        $signUrls = [];
        if (is_string($raw) && $raw !== '') {
            $paths = json_decode($raw, true);
            if (is_array($paths)) {
                foreach ($paths as $p) {
                    if (!is_string($p) || $p === '') {
                        continue;
                    }
                    $u = $this->_uploadService->getSignUrl($p);
                    if (is_string($u) && $u !== '') {
                        $signUrls[] = $u;
                    }
                }
            }
        }
        return $signUrls;
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

        $redisKey = self::REDIS_KEY_PROMPT_FOR_ANALYZE_TEXT . ':' . $promptKey. ':' . $scene;
        $cached = $this->getCache($redisKey);

        $promptRow = null;
        $baseRuntimeVars = [];

        if (is_array($cached) && isset($cached['prompt_row'])) {
            $promptRow = $cached['prompt_row'];
            //$baseRuntimeVars = $cached['runtime_vars'];
        } else {
            $where = [
                'prompt_key' => $promptKey,
                'scene' => $scene,
                'version' => 'v0.1',
            ];
            $result = $this->_daoVnAiPromptTemplatesModel->select('system_prompt,user_prompt_template' , $where);
            if ($result === false) {
                return -7;
            }
            if(isset($result[0]) && !empty($result[0])){
                $promptRow = [
                    'system_prompt' => $result[0]['system_prompt'],
                    'user_prompt_template' => $result[0]['user_prompt_template'],
                    //'variables' => json_decode($result[0]['variables'], true),
                ];
            }else{
                return -6;
            }

//            $AnalyseType = $this->_daoVnAiAnalysisTypesModel->select('name , json_schema',['is_active' => 1]);
//            if ($AnalyseType === false) {
//                return -7;
//            }
//
//            foreach ($AnalyseType as $item) {
//                $baseRuntimeVars["{$item['name']}_schema"] = $item['json_schema'];
//            }

            $cacheData = [
                'prompt_row' => $promptRow,
                //'runtime_vars' => $baseRuntimeVars,
            ];
            $this->setCache($redisKey, $cacheData, self::REDIS_EXPIRE_BASE_TIME*24);
        }

        //$runtimeVars = $baseRuntimeVars;
        //$runtimeVars['diary_text'] = $text;

//        foreach ($promptRow['variables'] as $varName => $type) {
//            if (!array_key_exists($varName, $runtimeVars)) {
//                return false;
//            }
//        }

//        $replaceMap = [];
//
//        foreach ($promptRow['variables'] as $varName => $type) {
//            $value = $runtimeVars[$varName];
//
//            if ($type === 'json') {
//                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
//            } else {
//                $value = (string) $value;
//            }
//
//            $replaceMap['{{' . $varName . '}}'] = $value;
//        }

        $replaceMap['{{diary_text}}'] = $text;

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
     * 添加文本笔记【富文本】
     * @param   int     $uid
     * @param   string  $noteText
     * @return void
     */
    public function addTextNote($uid, $noteText){
        $data = [
            'user_id' => $uid,
            'note_type' => self::NOTE_TYPE_TEXT,
            'content' => $noteText,
        ];
        $result = $this->_daoVnNoteModel->insert($data);

        if ($result === false) {//入库失败
            return -7;
        } else {
            return $result;
        }
    }

    /**
     * 添加图片笔记
     * @param   int     $uid
     * @param   string  $noteText
     * @param   array  $imageUrls
     * @return void
     */
    public function addImagesNote($uid, $noteText, $imageUrls)
    {
        $data = [
            'user_id' => $uid,
            'note_type' => self::NOTE_TYPE_IMAGE,
            'content' => $noteText,
            'media_url' => json_encode($imageUrls),
        ];

        $result = $this->_daoVnNoteModel->insert($data);

        if ($result === false) {//入库失败
            return -7;
        } else {
            return $result;
        }
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
        ];

        $result = $this->_daoVnNoteModel->insert($data);

        if ($result === false) {//入库失败
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
     * @param string      $keywords  搜索关键字
     * @return array{list: array, pagination: array{has_next_page: bool, next_cursor: int|null}}
     */
    public function getNoteListByCursor($uid, $cursor = 0, $pageSize = 20, $filters = [], $keywords= '')
    {
        if(!empty(trim($keywords))){
            $list = $this->_daoVnNoteModel->getUserNoteListByCursorWithKeyword($uid, $cursor, $pageSize, $keywords);
        } else {
            $where = array_merge(['user_id' => $uid , 'is_deleted' => 0], $filters);
            if (!empty($cursor)) {
                $where['id'] = ['LT', (int)$cursor];
            }
            $columns = 'id,title,summary,note_type,media_url,is_analyzed,analyzed_at,created_at';
            $orderBy = 'id DESC';
            $list = $this->_daoVnNoteModel->select($columns, $where, $orderBy, $pageSize + 1);
        }

        if ($list === false) {
            return ['list' => [], 'pagination' => ['has_next_page' => false, 'next_cursor' => 0]];
        }
        $hasNext = count($list) > $pageSize;
        if ($hasNext) {
            $list = array_slice($list, 0, $pageSize);
        }

        if (!empty($list)) {
            foreach ($list as $index => $item) {
                if (isset($item['media_url'])) {
                    $list[$index]['media_url'] = $this->buildSignedMediaUrls($item['media_url']);
                }
            }
            $noteIds = array_column($list, 'id');
            $tags = $this->_daoVnNoteTagsModel->select(
                'id,name,note_id',
                [
                    'user_id' => $uid,
                    'note_id' => ['IN', $noteIds],
                    'is_deleted' => 0,
                ]
            );

            $tagMap = [];
            if ($tags !== false && !empty($tags)) {
                foreach ($tags as $tag) {
                    if (!isset($tag['note_id'])) {
                        continue;
                    }
                    $noteId = $tag['note_id'];
                    unset($tag['note_id']);
                    if (!isset($tagMap[$noteId])) {
                        $tagMap[$noteId] = [];
                    }
                    $tagMap[$noteId][] = $tag;
                }
            }

            foreach ($list as $index => $item) {
                $noteId = $item['id'];
                $list[$index]['tags'] = isset($tagMap[$noteId]) ? $tagMap[$noteId] : [];
            }
        }

        $nextCursor = $hasNext ? end($list)['id'] : 0;
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
            return -7;
        }

        // 软删除关联的AI分析记录
        $this->_noteAiAnalysisService->softDeleteByNoteId($noteId);

        // 软删除关联的标签记录
        $tagData = [
            'is_deleted' => 1,
        ];
        $tagWhere = [
            'note_id' => $noteId,
        ];
        $this->_daoVnNoteTagsModel->softDelete($tagData, $tagWhere);

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
        return $result === false ? -7 : $result;
    }

}
