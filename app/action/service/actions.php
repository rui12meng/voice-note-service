<?php
namespace Action\Service;


/**
 * 日记行动服务
 * @author mengrui
 * $Id: actions.php $
 */

class Actions
{
    const REDIS_KEY_EXEC_HABITS_DATA = 'voice-note-service:exec_habits';

    private $_daoVnActionsModel;
    private $_daoVnNoteAiAnalyzeModel;
    private $_daoVnNotesModel;
    private $_daoVnHabitsModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_daoVnActionsModel = \Lsf\Loader::Model('DaoVnActions',true);
        $this->_daoVnNoteAiAnalyzeModel = \Lsf\Loader::Model('DaoVnNoteAiAnalysis', true);
        $this->_daoVnNotesModel = \Lsf\Loader::Model('DaoVnNotes', true);
        $this->_daoVnHabitsModel = \Lsf\Loader::Model('DaoVnHabits', true);
    }

    /**
     * 获取用户当日行动总数量
     * @param  int $uid    用户ID
     * @param  string $execDay  行动日期
     * @return void
     */
    public function countTodayActions($uid, $execDay){
        $where = [
            'user_id' => $uid,
            'due_date' => $execDay,
            'is_deleted' => 0,
        ];
        //先查询日记是否属于该用户
        $actionsNum = $this->_daoVnNotesModel->count('id', $where);
        if($actionsNum === false){
            return -7;
        }
        return $actionsNum;

    }

    /**
     * 根据笔记id删除笔记下关联的全部行动
     * @param  int $uid    用户ID
     * @param  int $noteId 日记ID
     * @param  string $structType  模块类型
     * @return void
     */
    public function delActionsByNoteId($uid, $noteId, $structType)
    {
        //先查询日记是否属于该用户
        $notes = $this->_daoVnNotesModel->select('id', ['id' =>$noteId , 'user_id' => $uid, 'is_deleted' => 0 ]);
        if($notes === false){
            return -7;
        }
        if(empty($notes)){ //数据为空或无权限或日记已删除
            return -6;
        }

        $data = ['is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s')];
        $where = [
            'note_id' => $noteId,
            'analysis_type_name' => $structType,
        ];
        $this->_daoVnNoteAiAnalyzeModel->begin();
        $result = $this->_daoVnNoteAiAnalyzeModel->update($data, $where);
        if($result === false){
            $this->_daoVnNoteAiAnalyzeModel->rollback();
            return -7;
        }
        $rows = $this->_daoVnActionsModel->softDelete(['is_deleted' => 1], ['note_id' => $noteId,'user_id' => $uid]);
        if($rows === false){
            $this->_daoVnNoteAiAnalyzeModel->rollback();
            return -7;
        }
        $this->_daoVnNoteAiAnalyzeModel->commit();
        if(is_int($rows) && ($rows >= 0)){
            // 静默忽略数据不存在的情况，统一返回1
            return 1;
        }else{
            return -5; //未知错误
        }
    }

    /**
     * 用户根据行动id删除行动
     * @param  int $uid    用户ID
     * @param  int $actionId 行动ID
     * @return void
     */
    public function deleteActionById($uid, $actionId){
        $data = ['is_deleted' => 1];
        $where = [
            'id' => $actionId,
            'user_id' => $uid,
        ];
        $result = $this->_daoVnActionsModel->update($data, $where);

        if($result === false){
            return -7;
        }
        //若不存在或已删除，静默忽略，不报错
        if (empty($result) || int($result) >= 0) {
            return 0;
        }else{
            return -6;
        }
    }

    /**
     * 根据行动id编辑行动执行日期
     * @param  int $uid    用户ID
     * @param  int $actionId 行动ID
     * @param  string $actionDate  执行日期
     * @return void
     */
    public function editActionById($uid, $actionId, $actionDate){
        $data = ['due_time' => $actionDate];
        $where = [
            'id' => $actionId,
            'user_id' => $uid,
        ];
        $result = $this->_daoVnActionsModel->update($data, $where);

        if($result === false){
            return -7;
        }
        return $result;
    }

    /**
     * 根据行动id编辑行动完成状态
     * @param  int $uid    用户ID
     * @param  int $actionId 行动ID
     * @param  int $status  状态值
     * @return void
     */
    public function editActionStatus($uid, $actionId, $status){
        // todo 优先查询该行动是否为习惯任务
        $actionInfo = $this->_daoVnActionsModel->find('habit_id, due_time, status, is_deleted',['id' => $actionId]);
        // todo 说明已删除
        if(isset($actionInfo['is_deleted']) && (int)$actionInfo['is_deleted'] === 1){
            //直接返回成功
            return 0;

        }
        // todo 说明已打卡
        if(isset($actionInfo['status']) && (int)$actionInfo['status'] === 1){
            //直接返回成功
            return 0;

        }

        //todo 说明是习惯，需要维护 current_streak 的标准逻辑
        if( isset($actionInfo['habit_id']) && is_numeric($actionInfo['habit_id']) && (int)$actionInfo['habit_id'] >0 ){
            $execDate = (new DateTime($actionInfo['due_time']))->format('Y-m-d');
            if($execDate === date('Y-m-d')){
                //todo 更新 user_habits（核心逻辑）
                $this->_daoVnHabitsModel->editHabitsByStreak($actionInfo['habit_id'], $actionInfo['due_time']);
            }
        }

        $data = ['status' => (int)$status];
        $where = [
            'id' => $actionId,
            'user_id' => $uid,
        ];
        $result = $this->_daoVnActionsModel->update($data, $where);

        if($result === false){
            return -7;
        }
        return $result;
    }

    /**
     * 用户级行动列表
     * @param  int $uid    用户ID
     * @param  int $cursor  游标
     * @param  int $pageSize 每页数量
     * @param array $filters 查询条件数组
     * @return void
     */
    public function actionList($uid, $cursor = null, $pageSize = 20, $filters = []){
        $where = [
            'user_id' => $uid,
            'is_deleted' => 0,
        ];
        $where = array_merge($where, $filters);
        if (!empty($cursor)) {
            $where['id'] = ['LE', (int)$cursor];
        }

        $columns = 'id, title, status, due_time';
        $orderBy = 'id DESC';
        $list = $this->_daoVnActionsModel->select($columns, $where, $orderBy, $pageSize+1);

        if($list === false){
            return -7;
        }

        $hasNext = count($list) > $pageSize;
        if ($hasNext) {
            $list = array_slice($list, 0, $pageSize);
        }

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
     * 用户笔记级行动列表
     * @param  int $uid    用户ID
     * @param  int $cursor  游标
     * @param  int $pageSize 每页数量
     * @param array $filters 查询条件数组
     * @return void
     */
    public function noteActionList($uid, $cursor = null, $pageSize = 20, $filters = []){

        //todo 1. 先验证模块是否存在
        $result = $this->_daoVnNoteAiAnalyzeModel->select('is_deleted',['note_id' =>$filters['note_id'], 'analysis_type_name' => 'actions']);
        if($result === false){
            return -7;
        }
        if(isset($result[0]['is_deleted']) && $result[0]['is_deleted'] === 1){
            return [];
        }
        $result = $this->actionList($uid, $cursor , $pageSize , $filters);
        return $result;
    }

    /**
     * 将特定某天要执行的习惯同步到行动表
     * @param int $uid      用户ID
     * @param string $execTime 执行时间（Y-m-d）
     * @return int 成功返回rows || 1，失败返回-7
     */
    public function syncExecHabitsToActions($uid, $execTime)
    {
        //todo 先查询是否已经同步，（仅同步一次），因为习惯修改与后续添加对历史数据不影响；
        $habits = $this->_daoVnActionsModel->count('habit_id',['user_id' => $uid, 'due_date' => $execTime, 'habit_id' => ['GT', 0]]);
        if($habits === false){
            return -7;
        }
        // todo 已写入，无需重复写入
        if(isset($habits) && $habits > 0){
            return 1;
        }

        //todo  根据 uid 与当前执行时间，查询需要展示的习惯并写入 actions 表
        // 获取今日应执行的习惯列表
        $daoHabits = \Lsf\Loader::Model('DaoVnHabits', true);
        $habits = $daoHabits->getExecHabitsList($uid, $execTime);
        if ($habits === false) {
            return -7;
        }

        if (empty($habits)) {
            // 今日无习惯需同步，视为成功
            return 1;
        }

        $actionData = [];
        foreach ($habits as $key => $habit) {
            $actionData[$key] = [
                'user_id'    => $uid,
                'note_id'    => $habit['note_id'],
                'habit_id'   => $habit['id'],
                'title'      => $habit['habit_name'],
                'streak'     => $habit['streak'],
                'status'     => 0,          // 0: 待执行
                'due_time'   => $execTime,
            ];
        }
        if(!empty($actionData)){
            $res = $this->_daoVnActionsModel->batchInsert($actionData);
            if ($res === false) {
                return -7;
            }
            return $res;
        }else{
            return 1;
        }
    }

    /**
     * 全量更新笔记下的行动：先删除旧数据，再批量插入新数据
     * @param  int   $uid       用户ID
     * @param  int   $noteId    笔记ID
     * @param  array $actions   待写入的行动数组，元素结构：
     *                           [
     *                               'name'  => '行动名称',
     *                               'title' => '行动标题（可选）',
     *                               'due_time' => '截止日期（Y-m-d，可选）',
     *                           ]
     * @return int 成功返回1，失败返回-7
     */
    public function saveActionsByNoteId($uid, $noteId, array $actions)
    {
        // 校验笔记归属
        $notes = $this->_daoVnNotesModel->select('id', ['id' => $noteId, 'user_id' => $uid, 'is_deleted' => 0]);
        if ($notes === false) {
            return -7;
        }
        if (empty($notes)) {
            return -6; // 无权限或笔记已删除
        }

        $this->_daoVnActionsModel->begin();

        // 1. 软删除旧行动
        $delRows = $this->_daoVnActionsModel->softDelete(
            ['is_deleted' => 1],
            ['note_id' => $noteId, 'user_id' => $uid]
        );
        if ($delRows === false) {
            $this->_daoVnActionsModel->rollback();
            return -7;
        }

        // 2. 若无新数据，直接提交并返回成功
        if (empty($actions)) {
            $this->_daoVnActionsModel->commit();
            return 1;
        }

        // 3. 组装待插入数据
        $insertData = [];
        foreach ($actions as $item) {
            $row = [
                'user_id'  => $uid,
                'note_id'  => $noteId,
                'title'    => $item['title']  ?? '',
                'status'   => $item['status']  ?? 0,
                'due_time' => $item['date'] ?? null,
            ];
            $insertData[] = $row;
        }

        // 4. 批量插入
        $res = $this->_daoVnActionsModel->batchInsert($insertData);
        if ($res === false) {
            $this->_daoVnActionsModel->rollback();
            return -7;
        }

        $this->_daoVnActionsModel->commit();
        return 1;
    }

    /**
     * 设置缓存
     * @param  string $redisKey
     * @param  array  $data
     * @param  int    $expire
     * @return bool
     */
    private function _setCache($redisKey, $data, $expire = 0)
    {
        try {
            $redisValue = json_encode($data, JSON_UNESCAPED_UNICODE);
            // 默认过期时间
            if ( ! $expire) {
                $expire = self::REDIS_EXPIRE_TIME;
            }
            $result = \Lsf\Loader::plugin('RedisPool')->redis()->setex($redisKey, $expire, $redisValue);
            if ( ! $result) {
                \Lsf\Loader::plugin('Log')->error(9040510, [
                    'redis_key'     => $redisKey,
                    'call_function' => 'setex',
                    'result'        => $result,
                ]);

                return false;
            } else {
                return true;
            }
        } catch (\RedisException $e) {
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'redis_key'     => $redisKey,
                'call_function' => 'setex',
                'code'          => $e->getCode(),
                'message'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 读取缓存
     * @param  string  $redisKey
     * @return mixed
     */
    private function _getCache($redisKey)
    {
        try {
            $redisData = \Lsf\Loader::plugin('RedisPool')->redis()->get($redisKey);
            if ( ! empty($redisData)) {
                $data = json_decode($redisData, true);
                if (json_last_error() > 0) {
                    \Lsf\Loader::plugin('Log')->error(9040510, ['redis_key' => $redisKey, 'redis_data' => $redisData]);

                    return false;
                } else {
                    return $data;
                }
            }

            return false;
        } catch (\RedisException $e) {
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'redis_key'     => $redisKey,
                'call_function' => 'get',
                'code'          => $e->getCode(),
                'message'       => $e->getMessage(),
            ]);

            return false;
        }
    }


}