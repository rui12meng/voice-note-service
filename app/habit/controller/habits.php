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
        $this->_habitsService = \Lsf\Loader::service('Habits', false, APP_NAME_NOTE);

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

        $intervalNum = (int)$this->post('interval_num', true);
        if ($intervalNum <= 0) {
            //$this->error('间隔数必须为正整数');
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'interval_num > 0');
        }
        if (!isset($intervalNum) || empty($intervalNum)) {
            $intervalNum = 1;
        }

        $intervalUnit= $this->post('interval_unit', true);
        if (!isset($intervalUnit) || empty($intervalUnit)) {
            $intervalUnit = 'day';
        }
        $allowedUnits = ['day', 'week', 'month'];
        if (!in_array($intervalUnit, $allowedUnits, true)) {

            return $this->json(1003004,[]);
        }

        // 调用服务添加习惯
        $result = $this->_habitsService->addUserHabit($uid, $noteId, $habitName, $intervalNum, $intervalUnit);

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


}

