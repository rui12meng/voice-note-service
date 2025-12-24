<?php
namespace Habit\Controller;

/**
 * 用户习惯控制器
 * $Id: habits.php $
 * @author mengrui
 */

class Habits extends \App\Application
{
    /**
     * @var mixed
     */
    private $_habitsService;

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
        $this->_habitsService = \Lsf\Loader::service('Habit', false, APP_NAME_NOTE);

    }
    /**
     * 添加用户习惯
     * 用户可手动添加自己的长期习惯（非AI生成），可与日记内容关联（可选），但独立存在。
     * 每用户限制50个习惯（启用状态）；如果满50，不可再添加。需要友好提示。
     *
     * @return void
     */
    public function add()
    {
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;
        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            $noteId = 0;
        }

        $habitName = $this->post('habit_name', true);
        if (!isset($habitName) || empty($habitName)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_name');
        }

        if (mb_strlen($habitName) > 50) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_name long');
        }

        $habitDesc = $this->post('description', true);
        if (!isset($habitDesc) || empty($habitDesc)) {
            $habitDesc = '';
        }

        $frequencyType = $this->post('frequency_type', true);
        if (!isset($frequencyType) || empty($frequencyType)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'frequency_type long');
        }
        $frequencyConfig = $this->post('frequency_config', true);
        $allowedUnits = ['daily', 'weekly', 'monthly', 'interval'];
        if (!in_array($frequencyType, $allowedUnits, true)) {
            return $this->json(1003004,[]);
        }elseif(trim($frequencyType) == 'daily'){
            $frequencyConfig = ["times_per_day" => 1];
        }elseif(trim($frequencyType) == 'weekly'){
            $frequencyConfig = ["week_days" => $frequencyConfig];
        }elseif(trim($frequencyType) == 'monthly'){
            $frequencyConfig = ["month_days" => $frequencyConfig];
        }else{
            $frequencyConfig = ["interval_days" => $frequencyConfig , "anchor_date" => date('Y-m-d')];
        }

        // 调用服务添加习惯
        $result = $this->_habitsService->addUserHabit($uid, $noteId, $habitName, $habitDesc, $frequencyType, $frequencyConfig);

        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                case -5:
                    $eCode = 1003005;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode, []);

    }

    /**
     * 批量添加习惯
     * 用户可手动添加自己的长期习惯（非AI生成），可与日记内容关联（可选），但独立存在。
     * 每用户限制50个习惯（启用状态）；如果满50，不可再添加。需要友好提示。
     *
     * @return void
     */
    public function batchAdd()
    {
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;

        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            $noteId = 0;
        }
        // 获取 habits 数组参数
        $habits = $this->post('habits');
        if (!is_array($habits) || empty($habits)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habits');
        }

        // 检查用户习惯数量是否已达上限
        $count = $this->_habitsService->countUserActiveHabits($uid);
        if($count === false){
            return $this->json(ECODE_DATABASE_QUERY_FAIL , []);
        }
        if ($count >= 50) {
            return $this->json(1003005 , []);
        }

        // 限制单次批量添加数量，防止超限
        $maxCanAdd = 50 - $count;
        $habits = array_slice($habits, 0, $maxCanAdd);

        $success = [];
        $failed  = [];

        foreach ($habits as $habit) {
            // 校验必填字段
            if (!isset($habit['habit_name']) || empty($habit['habit_name'])) {
                $failed[] = ['habit' => $habit, 'reason' => '缺少 habit_name'];
                continue;
            }
            if (mb_strlen($habit['habit_name']) > 50) {
                $failed[] = ['habit' => $habit, 'reason' => 'habit_name 长度超过50'];
                continue;
            }

            $intervalNum = isset($habit['interval_num']) ? (int)$habit['interval_num'] : 1;
            if ($intervalNum <= 0) {
                $failed[] = ['habit' => $habit, 'reason' => 'interval_num 必须为正整数'];
                continue;
            }

            $intervalUnit = isset($habit['interval_unit']) ? $habit['interval_unit'] : 'day';
            $allowedUnits = ['day', 'week', 'month'];
            if (!in_array($intervalUnit, $allowedUnits, true)) {
                $failed[] = ['habit' => $habit, 'reason' => 'interval_unit 仅支持 day、week、month'];
                continue;
            }

            // 调用服务添加习惯
            $result = $this->_habitsService->addUserHabit($uid, $noteId, $habit['habit_name'], $intervalNum, $intervalUnit);
            if ($result>0) {
                $success[] = $result;
            } else {
                $failed[] = ['habit' => $habit, 'reason' => '添加失败，请重试'];
            }
        }

        $responseData = [
            'success' => $success,
            'failed'  => $failed
        ];

        return $this->json(ECODE_SUCCESS, $responseData);
    }

    /**
     * 批量保存习惯（含新增、修改、删除）
     * 用户可一次性提交全部习惯，系统对比后增量更新，最终保证启用状态≤50条
     * @return void
     */
    public function batchSave()
    {
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;

        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            $noteId = 0;
        }
        $habits = $this->post('habits');
        if (!is_array($habits)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habits');
        }

        // 1. 先取出用户当前所有启用习惯
        $existList = $this->_habitsService->getUserActiveHabits($uid);
        if ($existList === false) {
            return $this->json(ECODE_DATABASE_QUERY_FAIL, []);
        }
        $existMap = array_column($existList, null, 'habit_id');   // 以 habit_id 为键

        // 2. 分类：新增、修改、删除
        $toAdd    = [];   // 新增
        $toUpdate = [];   // 修改
        $keepIds  = [];   // 需要保留的 habit_id
        foreach ($habits as $row) {
            // 统一校验
            if (empty($row['habit_name']) || mb_strlen($row['habit_name']) > 50) {
                return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_name 非法');
            }
            $intervalNum = isset($row['interval_num']) ? (int)$row['interval_num'] : 1;
            if ($intervalNum <= 0) {
                return $this->errParamMissing(ECODE_PARAM_MISSING, 'interval_num 必须为正整数');
            }
            $intervalUnit = isset($row['interval_unit']) ? $row['interval_unit'] : 'day';
            $allowedUnits = ['day', 'week', 'month'];
            if (!in_array($intervalUnit, $allowedUnits, true)) {
                return $this->errParamMissing(ECODE_PARAM_MISSING, 'interval_unit 非法');
            }

            if (empty($row['habit_id'])) {
                // 新增
                $toAdd[] = [
                    'habit_name'    => $row['habit_name'],
                    'interval_num'  => $intervalNum,
                    'interval_unit' => $intervalUnit,
                    'note_id'       => isset($row['note_id']) ? (int)$row['note_id'] : 0,
                ];
            } else {
                // 修改 or 保留
                if (!isset($existMap[$row['habit_id']])) {
                    return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_id 不存在');
                }
                $toUpdate[] = [
                    'habit_id'      => $row['habit_id'],
                    'habit_name'    => $row['habit_name'],
                    'interval_num'  => $intervalNum,
                    'interval_unit' => $intervalUnit,
                    'note_id'       => isset($row['note_id']) ? (int)$row['note_id'] : 0,
                ];
                $keepIds[]  = $row['habit_id'];
            }
        }

        // 3. 计算最终启用数量是否超限
        $finalCount = count($existList) + count($toAdd) - (count($existList) - count($keepIds));
        if ($finalCount > 50) {
            return $this->json(1003005, []);   // 超过50条
        }

        // 4. 执行数据库变更
        $this->_habitsService->beginTransaction();
        try {
            // 4.1 删除未再提交的习惯（软删或真删，按业务）
            $delIds = array_diff(array_keys($existMap), $keepIds);
            if ($delIds) {
                $this->_habitsService->deleteUserHabits($uid, $delIds);
            }

            // 4.2 批量新增
            foreach ($toAdd as $add) {
                $this->_habitsService->addUserHabit(
                    $uid,
                    $add['note_id'],
                    $add['habit_name'],
                    $add['interval_num'],
                    $add['interval_unit']
                );
            }

            // 4.3 批量修改
            foreach ($toUpdate as $upd) {
                $this->_habitsService->updateUserHabit(
                    $uid,
                    $upd['habit_id'],
                    $upd['note_id'],
                    $upd['habit_name'],
                    $upd['interval_num'],
                    $upd['interval_unit']
                );
            }

            $this->_habitsService->commit();
        } catch (\Exception $e) {
            $this->_habitsService->rollback();
            return $this->json(ECODE_DATABASE_QUERY_FAIL, []);
        }

        return $this->json(ECODE_SUCCESS, []);
    }

}

