<?php
namespace Action\Controller;

/**
 * 用户行动项控制器
 * $Id: actions.php $
 * @author mengrui
 */

class Actions extends \App\Application
{
    const USER_ACTION_COMPLETE = 1;
    const USER_ACTION_CANCEL = 0;
    /**
     * @var mixed
     */
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
        $this->_actionsService = \Lsf\Loader::service('Actions', false, APP_NAME_NOTE);

    }

    /**
     * todo 说明：行动项全部来源于笔记
     * 删除某日记下关联的全部行动（即删除日记下的整个行动模块）
     * 对标笔记智能分页结果页面其他模块功能
     * 采取软删除（分析模块），同时软删除行动项
     * @param  void
     * @return void
     */
    public function delByNote(){
        //1. 首先软删除分析模块 2. 在删除关联行动
        $uid = 101;
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }
        $structType = 'actions';
        $result = $this->_actionsService->delActionsByNoteId($uid, $noteId, $structType);

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
     * 用户根据行动ID删除行动
     * 采取软删除
     * @param  void
     * @return void
     */
    public function delete(){

        $uid = 101;
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
     * 用户主动行为操作完成任务
     * todo（说明：如果是习惯，计入完成习惯打卡统计）
     * @param  void
     * @return void
     */
    public function cancel(){
        $uid = 101;
        $actionId = $this->post('action_id', true);
        if ( ! isset($actionId) || empty($actionId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'action_id');
        }

        $result = $this->_actionsService->editActionStatus($uid, $actionId, self::USER_ACTION_CANCEL);

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
     * 用户主动行为操作取消任务
     * @param  void
     * @return void
     */
    public function complete(){
        $uid = 101;
        $actionId = $this->post('action_id', true);
        if ( ! isset($actionId) || empty($actionId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'action_id');
        }

        $result = $this->_actionsService->editActionStatus($uid, $actionId, self::USER_ACTION_COMPLETE);

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
        $uid = 101;
        $actionDate = $this->post('due_date', true);
        if ( ! isset($actionDate) || empty($actionDate)) {
            $actionDate = date('Y-m-d');
        }

        $status = $this->post('due_status', true);
        if ( ! isset($status) || empty($status)) {
            $status = self::USER_ACTION_CANCEL;
        }

        //todo 同步今日习惯到任务表（仅待办需要同步）
        if($status === self::USER_ACTION_CANCEL){
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
        if ( ! isset($pageSize) || empty($pageSize) || $pageSize < 0 || !is_int($pageSize)) {
            $pageSize = 20;
        }
        $filters = [
            'due_date' => $actionDate,
            'status' => $status,
        ];
        $result = $this->_actionsService->actionList($uid, $cursor, $pageSize = 20, $filters);
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
     * 日记关联的行动列表（默认限制50条）
     * todo 日记下的全量行动（包括打卡/未打卡）
     * @param  void
     * @return void
     */
    public function nList(){
        $uid = 101;
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        $cursor = $this->post('cursor', true);
        if ( ! isset($cursor) || empty($cursor) || $cursor < 0 || !is_int($cursor)) {
            //游标（Base64 编码的 (created_at, id)）
            $cursor = null;
        }
        $pageSize = $this->post('limit', true);
        if ( ! isset($pageSize) || empty($pageSize) || $pageSize < 0 || !is_int($pageSize)) {
            $pageSize = 20;
        }

        $filters = [
            'note_id' => $noteId,
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

    /**
     * 全量更新日记关联行动任务（全量替换，一键保存）
     * todo 编辑&新增（有action_id的更新；无action_id则录入）
     * @param  void
     * @return void
     */
    public function save(){
        $uid = 101;
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }
        //tasks
        $actions = $this->post('actions', true);
        if ( ! isset($actions) || empty($actions)) {
            return $this->json(0, []);
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
                return $this->errParamMissing(ECODE_PARAM_MISSING, 'title/ date 缺失');
            }
            // title 长度超限
            if (mb_strlen($item['title']) > 100) {
                return $this->errParamMissing(ECODE_PARAM_MISSING, 'title 超过100字符');
            }
            // date 范围校验：仅允许当天到将来一个月内
            $date = \DateTime::createFromFormat('Y-m-d', $item['date']);
            if (!$date) {
                return $this->errParamMissing(ECODE_PARAM_MISSING, 'date 格式非法');
            }
            $now = new \DateTime();
            // 重置时分秒，确保只比较日期部分
            $now->setTime(0, 0, 0);
            $date->setTime(0, 0, 0);
            $interval = $now->diff($date);
            if ($interval->invert > 0 || $interval->days > 30) {
                return $this->errParamMissing(ECODE_PARAM_MISSING, 'date 不在当天到将来一个月时间范围内');
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
            return $this->errParamMissing(ECODE_PARAM_MISSING, '当日行动总数超过50个限制');
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
}
