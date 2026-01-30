<?php
namespace Action\Controller;

/**
 * 用户行动复盘控制器
 * $Id: reflections.php $
 * @author mengrui
 */

class Reflections extends \App\Application
{
    /**
     * @var mixed
     */
    private $_reflectionsService;
    private $_actionsService;

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
        $this->_reflectionsService = \Lsf\Loader::service('Reflections', false, APP_NAME_ACTION);
        $this->_actionsService = \Lsf\Loader::service('Actions', false, APP_NAME_ACTION);

    }

    /**
     * 日复盘
     * @param  void
     * @return void
     */
    public function daily(){
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        $type = 'daily';

        $date = $this->post('date', true);
        if ( ! isset($date) || empty($date)) {
            $date = date('Y-m-d');
        }
        /*
         * "pending_actions": {
    "mode": "all",        // "all" 或 "selected"
    "action_ids": []      // mode="selected" 时必填；mode="all" 时可为空或忽略
  },
  "completed_actions": {
    "mode": "selected",
    "action_ids": [102, 105]
  }*/
        $pendingActions = $this->post('pending_actions', true);
        if (!isset($pendingActions) || empty($pendingActions)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'pending_actions');
        }
        $completedActions = $this->post('completed_actions', true);
        if (!isset($completedActions) || empty($completedActions)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'completed_actions');
        }

        $actionIds = $this->getReflectionActionsIds($uid, $date, $pendingActions, $completedActions);

        if ( ! isset($actionIds) || empty($actionIds)) {
            return $this->json(1004006, []);
        }
        //满意度评分
        $satisfaction = $this->post('satisfaction', true);
        if (!isset($satisfaction) || empty($satisfaction)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'satisfaction');
        }
        //主情绪
        $emotion = $this->post('emotion', true);
        if (!isset($emotion) || empty($emotion)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'emotion');
        }

        //todo 需要确认自我评价是否必传
        $summary = $this->post('summary' , true);
        if (!isset($summary) || empty($summary)) {
            $summary = '';
        }

        //是否同步到日记
        $syncToNote = $this->post('sync_to_note' , true);
        if (!isset($syncToNote) || empty($syncToNote)) {
            $syncToNote = 1;
        }

        //是否生成下一步计划
        $nextAction= $this->post('next_action' , true);
        if (!isset($nextAction) || empty($nextAction)) {
            $syncToNote = 1;
        }

        $result = $this->_reflectionsService->createRetrospectiveWithAIAnalysis($uid, $type, $date, $actionIds, $satisfaction, $emotion, $summary, $syncToNote, $nextAction);

        //todo 根据actionIds获取具体行动列表



        $eCode = ECODE_SUCCESS;
        return $this->json($eCode, []);

    }

    /**
     * 复盘行动ID参数处理
     * @param  void
     * @return void
     */
    private function getReflectionActionsIds($uid, $date, $pending_cfg, $completed_cfg){
        $actionIds = [];
        // 处理未完成任务
        if($pending_cfg['mode'] == 'all'){
            // todo 查询全部未完成ids
            $result = $this->_actionsService->getIds($uid, $date, 0);
            $actionIds = array_merge($actionIds, $result);

        }else{ // selected
            $actionIds = array_merge($actionIds , $pending_cfg['action_ids']);
        }

        // 处理已完成任务
        if($completed_cfg['mode'] == 'all'){
            // todo 查询全部已完成ids
            $result = $this->_actionsService->getIds($uid, $date, 1);
            $actionIds = array_merge($actionIds, $result);

        }else{
            $actionIds = array_merge($actionIds , $completed_cfg['action_ids']);
        }

        return $actionIds;
    }

    /**
     * 周复盘
     * @param  void
     * @return void
     */
    public function weekly(){
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }
        //actions
        $actions = $this->post('actions', true);

        if (!isset($actions)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'actions');
        }

        if (empty($actions)) {
            //todo 删除全部
            $this->_actionsService->saveActionsByNoteId($uid, $noteId, []);
            return $this->json(0, []);
        }

        if (!is_array($actions)) {
            return $this->json(1004001, []);
        }
        // 过滤掉 content 或 due_date 为空的任务
        $validActions = [];

        foreach ($actions as $item) {
            // 检查并修正 status 字段
            if (!isset($item['status'])) {
                $item['status'] = 0;
            } elseif (!in_array((int)$item['status'], [0, 1], true)) {
                $item['status'] = 0;
            }
            // 必填字段缺失
            if (empty($item['title']) || empty($item['date'])) {
                return $this->errParamMissing(ECODE_PARAM_MISSING, 'title/ date');
            }
            // title 长度超限
            if (mb_strlen($item['title']) > 50) {
                return $this->json(1004002, []);
            }
            // date 范围校验：仅允许当天到将来一个月内
            $date = \DateTime::createFromFormat('Y-m-d', $item['date']);
            if (!$date) {
                return $this->json(1004003, []);
            }
            $now = new \DateTime();
            // 重置时分秒，确保只比较日期部分
            $now->setTime(0, 0, 0);
            $date->setTime(0, 0, 0);
            $interval = $now->diff($date);
            if ($interval->invert > 0 || $interval->days > 30) {
                return $this->json(1004004, []);
            }
            $validActions[] = $item;
        }
        if (count($validActions) > 50) {
            return $this->json(1004000, []);
        }

        // 校验当日行动数量：数据库当日行动 + 当前添加的当日行动 不能超过50个
        $today = date('Y-m-d');
        $todayCnt = 0;
        foreach ($validActions as $item) {
            if ($item['date'] === $today) {
                $todayCnt++;
            }
        }
        $actionsCnt = $this->_actionsService->countTodayActions($uid, $today);
        if (($actionsCnt + $todayCnt) >= 50) {
            return $this->json(1004005, []);
        }

        // 调用服务层：全量更新（含新增、编辑、删除）
        $result = $this->_actionsService->saveActionsByNoteId($uid, $noteId, $validActions);

        $eCode = ECODE_SUCCESS;
        if (is_int($result) && $result < 0) {
            switch ($result) {
                // 数据库异常
                case -6:
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                // 未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode, []);

    }

    /**
     * 用户根据行动ID删除行动
     * 采取软删除
     * @param  void
     * @return void
     */
    public function delete(){

        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        $actionId = $this->post('action_id', true);
        if ( ! isset($actionId) || empty($actionId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'action_id');
        }

        $result = $this->_actionsService->deleteActionById($uid, $actionId);

        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -6:
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }
        return $this->json($eCode , []);

    }

    /**
     * 用户根据行动ID更新行动数据
     * @param  void
     * @return void
     */
    public function edit(){
        $uid = 101;
        $actionId = $this->post('action_id', true);
        if ( ! isset($actionId) || empty($actionId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'action_id');
        }

        /*$dateOption = $this->post('date_option', true);
        if(isset($dateOption) && !empty($dateOption) && !in_array((int)$dateOption, [1,2,3], true)){
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'date_option 非法');
        }*/
        $actionDate = $this->post('date', true);
        if ( ! isset($actionDate) || empty($actionDate)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'date');
        }

        $result = $this->_actionsService->editActionById($uid, $actionId, $actionDate);

        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -6:
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }
        return $this->json($eCode , []);
    }

    /**
     * 用户行动列表（待办/完成）
     * 支持按日期查询 /默认当日/默认待办
     * todo 如果是习惯，需要算法算出坚持次数
     * todo 需要同步今日习惯到任务表
     * @param  void
     * @return void
     */
    public function uList(){
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $actionDate = $this->post('due_date', true);
        if ( ! isset($actionDate) || empty($actionDate)) {
            $actionDate = date('Y-m-d');
        }

        $status = $this->post('due_status', true);
        if ( ! isset($status) || empty($status)) {
            $status = self::USER_ACTION_CANCEL;
        }

        //todo 同步今日习惯到任务表（仅待办需要同步）/按查看日期仅同步一次
        if($status === self::USER_ACTION_CANCEL && $actionDate <= date('Y-m-d')){
            // todo 调用服务：获取今日需展示的习惯并自动落库到 actions
            $this->_actionsService->syncExecHabitsToActions($uid, $actionDate);
            // todo 如果同步习惯失败，不报错，不阻塞
        }

        $cursor = $this->post('cursor', true);
        if ( ! isset($cursor) || empty($cursor) || $cursor < 0 || !is_int($cursor)) {
            //游标（Base64 编码的 (created_at, id)）
            $cursor = '';
        }
        $pageSize = $this->post('limit', true);
        if ( ! isset($pageSize) || empty($pageSize) || $pageSize < 0 || !is_numeric($pageSize)) {
            $pageSize = 20;
        }
        $filters = [
            'due_date' => $actionDate,
            'status' => $status,
        ];
        $result = $this->_actionsService->actionList($uid, $cursor, $pageSize, $filters);
        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode , $result);

    }


}
