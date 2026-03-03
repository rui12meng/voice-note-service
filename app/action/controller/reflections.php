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
     * 行动复盘
     * @param  void
     * @return void
     */
    public function submit(){
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        $startDate = $this->post('start_date', true);
        if ( ! isset($startDate) || empty($startDate)) {
            $startDate = date('Y-m-d');
        }
        $endDate = $this->post('end_date', true);
        if ( ! isset($endDate) || empty($endDate)) {
            $endDate = date('Y-m-d');
        }
        if(strtotime($startDate) > strtotime($endDate)){
            return $this->json( 1004007 , []);
        }
        if($startDate === $endDate){
            $type = 'daily';
        }else{
            $type = 'weekly';
        }

        $completedActions = $this->post('completed_actions', true);
        if (!isset($completedActions) || empty($completedActions)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'completed_actions');
        }

        $actionIds = $this->getReflectionActionsIds($uid, $startDate, $endDate, [], $completedActions);

        if ( ! isset($actionIds) || empty($actionIds)) {
            return $this->json(1004006, []);
        }
        //满意度评分
        $satisfaction = $this->post('satisfaction', true);
        if (!isset($satisfaction) || empty($satisfaction)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'satisfaction');
        }
        //todo 需要确认自我评价是否必传
        $summary = $this->post('summary' , true);
        if (!isset($summary) || empty($summary)) {
            $summary = '';
        }

        // todo 需要判断用户是否还有分析权限？有-分析，无-存储不分析？
        $result = $this->_reflectionsService->createAndAIAnalysis($uid, $type, $startDate, $endDate, $actionIds, $satisfaction, $summary );

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
        return $this->json($eCode, []);

    }

    /**
     * 日复盘（V1.2）
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
    private function getReflectionActionsIds($uid, $startDate, $endDate, $pending_cfg, $completed_cfg){
        $actionIds = [];

        // 处理未完成任务
        if (!empty($pending_cfg)) {
            if (isset($pending_cfg['mode']) && $pending_cfg['mode'] == 'all') {
                $result = $this->_actionsService->getIds($uid, $startDate, $endDate, 0);
                $actionIds = array_merge($actionIds, $result);
            } elseif (isset($pending_cfg['action_ids']) && is_array($pending_cfg['action_ids'])) {
                $actionIds = array_merge($actionIds, $pending_cfg['action_ids']);
            }
        }

        // 处理已完成任务
        if (!empty($completed_cfg)) {
            if (isset($completed_cfg['mode']) && $completed_cfg['mode'] == 'all') {
                $result = $this->_actionsService->getIds($uid, $startDate, $endDate, 1);
                $actionIds = array_merge($actionIds, $result);
            } elseif (isset($completed_cfg['action_ids']) && is_array($completed_cfg['action_ids'])) {
                $actionIds = array_merge($actionIds, $completed_cfg['action_ids']);
            }
        }

        return array_unique($actionIds);
    }

    /**
     * 周复盘（V1.2）
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
     * 用户根据复盘ID删除复盘记录
     * 采取软删除
     * @param  void
     * @return void
     */
    public function delete(){

        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        $reflectionId = $this->post('reflection_id', true);
        if ( ! isset($reflectionId) || empty($reflectionId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'reflection_id');
        }

        $result = $this->_reflectionsService->deleteReflectionById($uid, $reflectionId);

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
     * 用户复盘列表
     * 支持按日期查询 /默认当日
     * @param  void
     * @return void
     */
    public function lists(){
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }

        $startDate = $this->post('start_date', true);
        if ( ! isset($startDate) || empty($startDate)) {
            $startDate = date('Y-m-d');
        }

        $endDate = $this->post('end_date', true);
        if ( ! isset($endDate) || empty($endDate)) {
            $endDate = date('Y-m-d');
        }

        if(strtotime($startDate) > strtotime($endDate)){
            return $this->json( 1004007 , []);
        }

        $cursor = $this->post('cursor', true);
        if ( ! isset($cursor) || empty($cursor) || $cursor < 0 || !is_int($cursor)) {
            //游标
            $cursor = '';
        }
        $pageSize = $this->post('limit', true);
        if ( ! isset($pageSize) || empty($pageSize) || $pageSize < 0 || !is_numeric($pageSize)) {
            $pageSize = 20;
        }
        //WHERE start_date <= ?  -- ? = query_end
        //  AND end_date >= ?    -- ? = query_start
        $filters = [
            'start_date' => ['ELT', $endDate],
            'end_date' => ['EGT', $startDate],
        ];
        $result = $this->_reflectionsService->reflectionList($uid, $cursor, $pageSize, $filters);
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

    /**
     * 用户根据复盘id查看复盘结果（详情）
     * @param  void
     * @return void
     */
    public function detail(){
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }
        $reflectionId = $this->post('reflection_id', true);
        if ( ! isset($reflectionId) || empty($reflectionId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'reflection_id');
        }

        $result = $this->_reflectionsService->getReflectionDetailById($uid, $reflectionId);
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
