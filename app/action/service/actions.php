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
    private $_daoVnHabitSyncRecordsModel;

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
        $this->_daoVnHabitSyncRecordsModel = \Lsf\Loader::Model('DaoVnHabitSyncRecords', true);
    }

    /**
     * 获取用户某日行动ID列表
     * @param  int $uid    用户ID
     * @param  string $startDate  开始日期
     * @param  string $endDate  结束日期
     * @param  int status 完成状态
     * @return void
     */
    public function getIds($uid, $startDate, $endDate, $status = 0){

        $where = [
            'user_id' => $uid,
            'due_date' => ['EGT', $startDate],
            'due_date' => ['ELT', $endDate],
            'status' => $status,
            'is_deleted' => 0,
        ];
        $result = $this->_daoVnActionsModel->select('id', $where);

        if($result === false){
            return [];
        }
        $actionIds = [];
        foreach ($result as $item){
            $actionIds[] = $item['id'];
        }
        return $actionIds;
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
        // 若不存在或已删除，静默忽略，不报错
        if (empty($result) || (int)($result) >= 0) {
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
        $data = ['due_date' => $actionDate];
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
    public function completeActionStatus($uid, $actionId, $status){
        $today    = new \DateTime('today');
        // todo 优先查询该行动权限
        $actionInfo = $this->_daoVnActionsModel->find('user_id, habit_id, due_date, status, is_deleted',$actionId);
        if($actionInfo === false){
            return -7;
        }
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
        if( isset($actionInfo['habit_id']) && is_numeric((int)$actionInfo['habit_id']) && (int)$actionInfo['habit_id'] >0 ){

            //todo 查询habit配置
            $habit = $this->_daoVnHabitsModel->find('last_done_date, interval_unit, interval_num, current_streak, streak_count', $actionInfo['habit_id']);
            if($habit === false){
                return -7;
            }

            $execDate = \DateTime::createFromFormat('Y-m-d', $actionInfo['due_date']);
            $today    = new \DateTime('today');
            // 打卡总次数
            $newStreakCount = $habit['streak_count'] + 1;

            //todo 只有当天操作完成，才记录连续，如果非当天表示历史补打卡，只记录打卡总次数，不变更当前连续性
            if ($execDate->format('Y-m-d') === $today->format('Y-m-d')) {
                // 计算周期天数
                $periodDays = 1; // 默认值
                switch (strtolower($habit['interval_unit'])) {
                    case 'day':
                        $periodDays = $habit['interval_num'];
                        break;
                    case 'week':
                        $periodDays = $habit['interval_num'] * 7;
                        break;
                    case 'month':
                        $periodDays = $habit['interval_num'] * 30;
                        break;
                    // default 已由初始值覆盖
                }
                // 计算 new_current_streak 当前连续打卡数
                if (empty($habit['last_done_date'])) {
                    $newCurrentStreak = 1;
                } else {
                    $diff = (strtotime($today) - strtotime($habit['last_done_date'])) / 86400;
                    $newCurrentStreak = ($diff == $periodDays) ? ($habit['current_streak'] + 1) : 1;
                }
                //todo 更新 user_habits（核心逻辑）事务处理
                $result = $this->_daoVnHabitsModel->editHabitsByStreak((int)$uid, (int)$actionId, (int)$actionInfo['habit_id'], (int)$newCurrentStreak, (int)$newStreakCount, (int)$status);

            // todo 补打卡（非执行时间打卡）
            }else{
                $data = [
                    'streak_count' => $newStreakCount,
                    'complete_time' => date('Y-m-d H:i:s'),
                ];
                $result = $this->_daoVnHabitsModel->editHabitCompletion($uid, $actionInfo['habit_id'], $actionId, $status, $data);

            }
        }else{
            $data = [
                'status' => (int)$status,
                'complete_time' => date('Y-m-d H:i:s'),
                ];
            $where = [
                'id' => $actionId,
                'user_id' => $uid,
            ];
            $result = $this->_daoVnActionsModel->update($data, $where);
        }
        if($result === false){
            return -7;
        }
        return $result;

    }

    /**
     * 根据行动id撤销行动完成状态【撤销完成】
     * todo v0.1版本习惯只记录打卡总次数，所以取消只更新总次数
     * @param  int $uid    用户ID
     * @param  int $actionId 行动ID
     * @param  int $status  状态值
     * @return void
     */
    public function cancelActionStatus($uid, $actionId, $status){
        // todo 优先查询该行动权限
        $actionInfo = $this->_daoVnActionsModel->find('user_id, habit_id, due_date, status, is_deleted',$actionId);
        if($actionInfo === false){
            return -7;
        }
        // todo 说明已删除
        if(isset($actionInfo['is_deleted']) && (int)$actionInfo['is_deleted'] === 1){
            //直接返回成功
            return 0;
        }
        // todo 说明本身就是待办任务，无需走撤销逻辑
        if(isset($actionInfo['status']) && (int)$actionInfo['status'] === 0){
            //直接返回成功
            return 0;
        }

        //todo 说明是习惯，需要维护 streak_count and current_streak 的标准逻辑
        if( isset($actionInfo['habit_id']) && is_numeric((int)$actionInfo['habit_id']) && (int)$actionInfo['habit_id'] >0 ){

            $habit = $this->_daoVnHabitsModel->find('interval_unit, interval_num, streak_count', $actionInfo['habit_id']);
            if($habit === false){
                return -7;
            }

            $execDate = \DateTime::createFromFormat('Y-m-d', $actionInfo['due_date']);
            $today    = new \DateTime('today');
            //todo 当天撤销：需重置连续状态
            if($execDate->format('Y-m-d') === $today->format('Y-m-d')){
                //todo 查找上上次完成的打卡日期
                $where = [
                    'habit_id' => $actionInfo['habit_id'],
                    'user_id' => $uid,
                    'status' => 1,
                    'due_date' => ['lt', $actionInfo['due_date']]
                ];
                $order = 'due_date DESC';
                $prevAction = $this->_daoVnActionsModel->select('due_date, streak' , $where, $order);
                if($prevAction === false){
                    return -7;
                }
                $newLastDoneDate = isset($prevAction[0]['due_date']) ? $prevAction[0]['due_date'] : null;

                //todo 重新计算 current_streak
                if ($newLastDoneDate === null) {
                    $newCurrentStreak = 0; // 或 0，因为今天没打
                }else {
                    //todo 直接查上上次 action 的 streak 值
                    $newCurrentStreak = isset($prevAction[0]['streak']) ? $prevAction[0]['streak'] : 0;
                }
                //todo  更新 habit
                $habitUpdate = [
                    'last_done_date' => $newLastDoneDate,
                    'current_streak' => $newCurrentStreak,
                    'streak_count'   => max(0, $habit['streak_count'] - 1),
                ];

                $result = $this->_daoVnHabitsModel->editHabitCompletion($uid, $actionInfo['habit_id'], $actionId, $status, $habitUpdate);
                if($result === false){
                    return -7;
                }

            }else{
                // todo 打卡总次数
                $newStreakCount = max(0, $habit['streak_count'] - 1);
                $data = [
                    'streak_count' => $newStreakCount,
                ];
                $result = $this->_daoVnHabitsModel->editHabitCompletion($uid, $actionInfo['habit_id'], $actionId, $status, $data);
                if($result === false){
                    return -7;
                }
            }

        // 待办
        }else{
            $data = [
                'status' => (int)$status,
            ];
            $where = [
                'id' => $actionId,
                'user_id' => $uid,
            ];
            $result = $this->_daoVnActionsModel->update($data, $where);
        }

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
    public function actionList($uid, $cursor = 0, $pageSize = 20, $filters = []){
        $where = [
            'user_id' => $uid,
            'is_deleted' => 0,
        ];
        $where = array_merge($where, $filters);
        if (!empty($cursor)) {
            $where['id'] = ['LT', (int)$cursor];
        }

        $columns = 'id, title, content, streak, status, due_date, habit_id';
        $orderBy = 'id DESC';
        $list = $this->_daoVnActionsModel->select($columns, $where, $orderBy, $pageSize+1);

        if($list === false){
            return -7;
        }

        $hasNext = count($list) > $pageSize;
        if ($hasNext) {
            $list = array_slice($list, 0, $pageSize);
        }

        // 聚合习惯打卡总次数
        $habitIds = [];
        foreach ($list as $item) {
            if (isset($item['habit_id']) && $item['habit_id'] > 0) {
                $habitIds[] = $item['habit_id'];
            }
        }

        if (!empty($habitIds)) {
            $habitIds = array_unique($habitIds);
            $habits = $this->_daoVnHabitsModel->select('id, streak_count', ['id' => ['IN', $habitIds]]);
            
            $habitMap = [];
            if ($habits !== false && !empty($habits)) {
                foreach ($habits as $h) {
                    $habitMap[$h['id']] = $h['streak_count'];
                }
            }

            foreach ($list as &$item) {
                if (isset($item['habit_id']) && isset($habitMap[$item['habit_id']])) {
                    $item['streak'] = $habitMap[$item['habit_id']];
                }
            }
        }

        $nextCursor = $hasNext ? end($list)['id'] : 0;

        return [
            'list' => $this->_replaceNullWithEmptyString($list),
            'pagination' => [
                'has_next_page' => $hasNext,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    /**
     * 对数组中「所有字段」的 null 值统一替换为空字符串
     * @param   array $array
     * @return  array
     */
    private function _replaceNullWithEmptyString(array $array)
    {
        if(!empty($array)){
            foreach ($array as &$row) {
                if (is_array($row)) {
                    foreach ($row as $key => $value) {
                        if ($value === null) {
                            $row[$key] = '';
                        }
                    }
                }
            }
        }
        return $array;
    }

    /**
     * 用户笔记级行动列表
     * @param  int $uid    用户ID
     * @param  int $cursor  游标
     * @param  int $pageSize 每页数量
     * @param array $filters 查询条件数组
     * @return void
     */
    public function noteActionList($uid, $cursor = 0, $pageSize = 20, $filters = []){

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
        $syncRecord = $this->_daoVnHabitSyncRecordsModel->count('id',['user_id' => $uid, 'sync_date' => $execTime]);
        if($syncRecord === false){
            return -7;
        }
        // todo 已写入，无需重复写入
        if(isset($syncRecord) && $syncRecord > 0){
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
                'content'    => $habit['habit_desc'],
                'streak'     => $habit['streak'],
                'status'     => 0,          // 0: 待执行
                'due_date'   => $execTime,
            ];
        }
        if(!empty($actionData)){
            $this->_daoVnActionsModel->begin();
            $res = $this->_daoVnActionsModel->batchInsert($actionData, true);
            if ($res === false) {
                $this->_daoVnActionsModel->rollback();
                return -7;
            }
            //todo 记录已同步
            $data = [
                'user_id' => $uid,
                'sync_date' => $execTime,
            ];
            $record = $this->_daoVnHabitSyncRecordsModel->insert($data);
            if ($record === false) {
                $this->_daoVnActionsModel->rollback();
                return -7; // 数据库错误
            }
            $this->_daoVnActionsModel->commit();
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
     *                               'due_date' => '截止日期（Y-m-d，可选）',
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
                'title'    => isset($item['title'])  ? $item['title'] : '',
                'status'   => isset($item['status'])  ? $item['status'] : 0,
                'due_date' => isset($item['date']) ? $item['date'] : null,
                'source'   => 'user',
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