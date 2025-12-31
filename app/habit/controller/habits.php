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
     * 用户添加习惯(V1.0版本，暂时保留)
     * 用户可手动添加自己的长期习惯（非AI生成），可与日记内容关联（可选），但独立存在。
     * 每用户限制50个习惯（启用状态）；如果满50，不可再添加。需要友好提示。
     *
     * @return void
     */
    public function addV2()
    {
        // 用户uid
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;
        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            $noteId = 0;
        }
        //习惯名称（限制30字符，不可为空）
        $habitName = $this->post('habit_name', true);
        if (!isset($habitName) || empty($habitName)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_name');
        }

        if (mb_strlen($habitName) > 30) { //字符限制
            return $this->json(1003000 , []);
        }
        //习惯描述（限制30字符，可空）
        $habitDesc = $this->post('description', true);
        if (!isset($habitDesc) || empty($habitDesc)) {
            $habitDesc = '';
        }
        if (mb_strlen($habitDesc) > 30) { //字符限制
            return $this->json(1003000 , []);
        }

        //开关启用状态；默认开启（0/1）
        $active = (int)$this->post('active', true);
        if (!isset($active) || empty($active)) {
            $active = 1;
        }
        if (!in_array($active, [0,1], true)) {
            return $this->json(1003001,[]);
        }

        //提醒时间
        $remindTime = $this->post('remind_time', true);
        if (!isset($remindTime) || empty($remindTime)) {
            $remindTime = "";
        } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $remindTime)) {
            return $this->json(1003002,[]);
        }

        //频率限制-类型
        $frequencyType = $this->post('frequency_type', true);
        if (!isset($frequencyType) || empty($frequencyType)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'frequency_type');
        }
        $allowedUnits = ['daily', 'weekly', 'monthly', 'interval'];
        if (!in_array($frequencyType, $allowedUnits, true)) {
            return $this->json(1003004,[]);
        }

        //频率限制-具体配置
        $frequencyConfig = $this->post('frequency_config', true);
    
        // 根据 frequencyType 校验并格式化 frequencyConfig
        switch ($frequencyType) {
            case 'daily':
                // 可为空，默认1次/天
                if (empty($frequencyConfig)) {
                    $frequencyConfig = ["days" => 1];
                } else {
                    $times = (int)$frequencyConfig;
                    if ($times < 1) {
                        return $this->json(1003003,[]);
                    }
                    $frequencyConfig = ["days" => $times];
                }
                break;

            case 'weekly':
                // 不可空，1～7之间的数字
                if (empty($frequencyConfig)) {
                    return $this->json(1003005,[]);
                }
                $day = (int)$frequencyConfig;
                if ($day < 1 || $day > 7) {
                    return $this->json(1003006,[]);
                }
                $frequencyConfig = ["days" => $day];
                break;

            case 'monthly':
                // 不可空，1～当月天数之间的数字
                if (empty($frequencyConfig)) {
                    return $this->json(1003005,[]);
                }
                $day = (int)$frequencyConfig;
                $maxDay = (int)date('t');
                if ($day < 1 || $day > $maxDay) {
                    return $this->json(1003007,[]);
                }
                $frequencyConfig = ["days" => $day];
                break;

            case 'interval':
                // 不可空，1～7之间的数字
                if (empty($frequencyConfig)) {
                    return $this->json(1003005,[]);
                }
                $interval = (int)$frequencyConfig;
                if ($interval < 1 || $interval > 7) {
                    return $this->json(1003006,[]);
                }
                $frequencyConfig = ["days" => $interval, "anchor_date" => date('Y-m-d')];
                break;

            default:
                return $this->json(ECODE_UNDEFINED_ERROR , []);
        }

        // 调用服务添加习惯
        $result = $this->_habitsService->addUserHabit($uid, $noteId, $habitName, $habitDesc, $remindTime, $active, $frequencyType, $frequencyConfig);

        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_INSERT_FAIL;
                    break;
                    //超限制
                case -5:
                    $eCode = 1003008;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode, []);

    }

    /**
     * 用户添加习惯
     * 用户可手动添加自己的长期习惯（非AI生成），可与日记内容关联（可选），但独立存在。
     * 每用户限制50个习惯（启用状态）；如果满50，不可再添加。需要友好提示。
     *
     * @return void
     */
    public function add(){
        // 用户uid
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;
        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            $noteId = 0;
        }
        //习惯名称（限制30字符，不可为空）
        $habitName = $this->post('habit_name', true);
        if (!isset($habitName) || empty($habitName)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_name');
        }

        if (mb_strlen($habitName) > 30) { //字符限制
            return $this->json(1003000 , []);
        }
        //习惯描述（限制30字符，可空）
        $habitDesc = $this->post('description', true);
        if (!isset($habitDesc) || empty($habitDesc)) {
            $habitDesc = '';
        }
        if (mb_strlen($habitDesc) > 30) { //字符限制
            return $this->json(1003000 , []);
        }

        //开关启用状态；默认开启（0/1）
        $active = (int)$this->post('active', true);
        if (!isset($active) || empty($active)) {
            $active = 1;
        }
        if (!in_array($active, [0,1], true)) {
            return $this->json(1003001,[]);
        }

        //提醒时间
        $remindTime = $this->post('remind_time', true);
        if (!isset($remindTime) || empty($remindTime)) {
            $remindTime = "";
        } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $remindTime)) {
            return $this->json(1003002,[]);
        }

        //频率配置（v0.1版本仅支持时间间隔配置）
        $intervalNum =  $this->post('interval_num', true);
        if (!isset($intervalNum) || empty($intervalNum)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'interval_num');
        }
        // 验证 intervalNum 为 1～30 之间的数字（包含 1 和 30）
        if (!is_numeric($intervalNum) || $intervalNum < 1 || $intervalNum > 30) {
            return $this->json(1003009, []);
        }

        $intervalUnit =  $this->post('interval_unit', true);
        if (!isset($intervalUnit) || empty($intervalUnit)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'interval_unit');
        }
        $allowedUnits = ['day', 'week', 'month'];
        if (!in_array($intervalUnit, $allowedUnits, true)) {
            return $this->json(1003010, []);
        }

        // 调用服务添加习惯
        $result = $this->_habitsService->addUserHabit($uid, $noteId, $habitName, $habitDesc, $remindTime, $active, $intervalNum, $intervalUnit);

        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_INSERT_FAIL;
                    break;
                //超限制
                case -5:
                    $eCode = 1003008;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode, []);
    }

    /**
     * 用户获取习惯详情
     * @param void
     * @return void
     */
    public function info()
    {
        // 用户uid
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;
        //habit_id 必传
        $habitId = $this->post('habit_id', true);
        if (!isset($habitId) || empty($habitId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_id');
        }
        // 调用服务获取习惯详情（含主表与配置表）
        $result = $this->_habitsService->getUserHabitDetail($uid, $habitId);

        $eCode = ECODE_SUCCESS;
        $responseData = [];

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_INSERT_FAIL;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }else{
            if(is_array($result) && !empty($result)){

                $responseData = [
                    'id' => (int)$habitId,
                    'habit_name' => $result['habit_name'] ?? '',
                    'habit_desc' => $result['habit_desc'] ?? '',
                    'interval_num' => $result['interval_num'] ?? '',
                    'interval_unit' => $result['interval_unit'] ?? '',
                    'note_title' => $result['note_title'] ?? '',
                ];
            }
        }

        return $this->json($eCode, $responseData);

    }

    /**
     * 用户修改习惯
     * @param void
     * @return void
     */
    public function editV2()
    {
        // 用户uid
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;

        $habitId = $this->post('habit_id', true);
        if (!isset($habitId) || empty($habitId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_id');
        }
        //习惯名称（限制30字符，不可为空）
        $habitName = $this->post('habit_name', true);
        if (!isset($habitName) || empty($habitName)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_name');
        }

        if (mb_strlen($habitName) > 30) { //字符限制
            return $this->json(1003000 , []);
        }
        //习惯描述（限制50字符，可空）
        $habitDesc = $this->post('description', true);
        if (!isset($habitDesc) || empty($habitDesc)) {
            $habitDesc = '';
        }
        if (mb_strlen($habitDesc) > 50) { //字符限制
            return $this->json(1003000 , []);
        }

        //开关启用状态；默认开启（0/1）
        $active = $this->post('active', true);
        // 允许0/1，但0不是“空值”，而是有效值；仅当未传参时才默认1
        if ($active === null) {
            $active = 1;
        } elseif (!in_array((int)$active, [0,1], true)) {
            return $this->json(1003001,[]);
        } else {
            $active = (int)$active;
        }

        //提醒时间
        $remindTime = $this->post('remind_time', true);
        if (!isset($remindTime) || empty($remindTime)) {
            $remindTime = "";
        } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $remindTime)) {
            return $this->json(1003002,[]);
        }

        //频率配置（v0.1版本仅支持时间间隔配置）
        $intervalNum =  $this->post('interval_num', true);
        if (!isset($intervalNum) || empty($intervalNum)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'interval_num');
        }
        // 验证 intervalNum 为 1～30 之间的数字（包含 1 和 30）
        if (!is_numeric($intervalNum) || $intervalNum < 1 || $intervalNum > 30) {
            return $this->json(1003009, []);
        }

        $intervalUnit =  $this->post('interval_unit', true);
        if (!isset($intervalUnit) || empty($intervalUnit)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'interval_unit');
        }
        $allowedUnits = ['day', 'week', 'month'];
        if (!in_array($intervalUnit, $allowedUnits, true)) {
            return $this->json(1003010, []);
        }

        // 调用服务添加习惯
        $result = $this->_habitsService->editUserHabit($uid, $habitId, $habitName, $habitDesc, $remindTime, $active, $intervalNum, $intervalUnit);

        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_INSERT_FAIL;
                    break;
                //习惯不存在或无权限
                case -4:
                    $eCode = 1003011;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode, []);

    }

    /**
     * 用户修改习惯
     * @param void
     * @return void
     */
    public function editV2()
    {
        // 用户uid
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;

        $habitId = $this->post('habit_id', true);
        if (!isset($habitId) || empty($habitId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_id');
        }
        //习惯名称（限制30字符，不可为空）
        $habitName = $this->post('habit_name', true);
        if (!isset($habitName) || empty($habitName)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_name');
        }

        if (mb_strlen($habitName) > 30) { //字符限制
            return $this->json(1003000 , []);
        }
        //习惯描述（限制50字符，可空）
        $habitDesc = $this->post('description', true);
        if (!isset($habitDesc) || empty($habitDesc)) {
            $habitDesc = '';
        }
        if (mb_strlen($habitDesc) > 50) { //字符限制
            return $this->json(1003000 , []);
        }

        //开关启用状态；默认开启（0/1）
        $active = $this->post('active', true);
        // 允许0/1，但0不是“空值”，而是有效值；仅当未传参时才默认1
        if ($active === null) {
            $active = 1;
        } elseif (!in_array((int)$active, [0,1], true)) {
            return $this->json(1003001,[]);
        } else {
            $active = (int)$active;
        }

        //提醒时间
        $remindTime = $this->post('remind_time', true);
        if (!isset($remindTime) || empty($remindTime)) {
            $remindTime = "";
        } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $remindTime)) {
            return $this->json(1003002,[]);
        }

        //频率限制-类型
        $frequencyType = $this->post('frequency_type', true);
        if (!isset($frequencyType) || empty($frequencyType)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'frequency_type');
        }
        $allowedUnits = ['daily', 'weekly', 'monthly', 'interval'];
        if (!in_array($frequencyType, $allowedUnits, true)) {
            return $this->json(1003004,[]);
        }

        //频率限制-具体配置
        $frequencyConfig = $this->post('frequency_config', true);

        // 根据 frequencyType 校验并格式化 frequencyConfig
        switch ($frequencyType) {
            case 'daily':
                // 可为空，默认1次/天
                if (empty($frequencyConfig)) {
                    $frequencyConfig = ["days" => 1];
                } else {
                    $times = (int)$frequencyConfig;
                    if ($times < 1) {
                        return $this->json(1003003,[]);
                    }
                    $frequencyConfig = ["days" => $times];
                }
                break;

            case 'weekly':
                // 不可空，1～7之间的数字
                if (empty($frequencyConfig)) {
                    return $this->json(1003005,[]);
                }
                $day = (int)$frequencyConfig;
                if ($day < 1 || $day > 7) {
                    return $this->json(1003006,[]);
                }
                $frequencyConfig = ["days" => $day];
                break;

            case 'monthly':
                // 不可空，1～当月天数之间的数字
                if (empty($frequencyConfig)) {
                    return $this->json(1003005,[]);
                }
                $day = (int)$frequencyConfig;
                $maxDay = (int)date('t');
                if ($day < 1 || $day > $maxDay) {
                    return $this->json(1003007,[]);
                }
                $frequencyConfig = ["days" => $day];
                break;

            case 'interval':
                // 不可空，1～7之间的数字
                if (empty($frequencyConfig)) {
                    return $this->json(1003005,[]);
                }
                $interval = (int)$frequencyConfig;
                if ($interval < 1 || $interval > 7) {
                    return $this->json(1003006,[]);
                }
                $frequencyConfig = ["days" => $interval, "anchor_date" => date('Y-m-d')];
                break;

            default:
                return $this->json(ECODE_UNDEFINED_ERROR , []);
        }

        // 调用服务添加习惯
        $result = $this->_habitsService->editUserHabit($uid, $habitId, $habitName, $habitDesc, $remindTime, $active, $frequencyType, $frequencyConfig);

        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_INSERT_FAIL;
                    break;
                //习惯不存在或无权限
                case -4:
                    $eCode = 1003011;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode, []);

    }

    /**
     * 根据 habit_id 软删除习惯（含规则数据）
     * @return void
     */
    public function delete()
    {
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;

        $habitId = $this->post('habit_id', true);
        if (!isset($habitId) || empty($habitId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'habit_id');
        }

        // 调用服务软删除
        $result = $this->_habitsService->softDeleteUserHabit($uid, $habitId);

        $eCode = ECODE_SUCCESS;
        if (is_int($result) && $result < 0) {
            switch ($result) {
                case -7:   // 数据库异常
                    $eCode = ECODE_DATABASE_DELETE_FAIL;
                    break;
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode, []);
    }

    /**
     * 用户习惯列表（游标分页 + 文本搜索）
     * 支持按名称/描述/日记摘要模糊搜索
     * 每页默认20条，游标偏移分页
     * @return void
     */
    public function list()
    {
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;

        // 搜索关键词，可选
        $keyword = $this->post('keyword', true);
        if (!is_string($keyword) || !isset($keyword) || empty($keyword)) {
            $keyword = '';
        }
        $keyword = trim($keyword);

        // 游标偏移：上一次返回的最后一条 habit_id，首次传 0
        $cursor = (int)$this->post('cursor', true);
        if ($cursor < 0) {
            $cursor = 0;
        }

        // 每页条数
        $pageSize = (int)$this->post('limit', true);
        if ($pageSize < 0 || !isset($pageSize) || empty($pageSize)) {
            $pageSize = 20;
        }

        // 调用服务获取列表
        $result = $this->_habitsService->getUserHabitList($uid, $keyword, $cursor, $pageSize);

        if ($result === false) {
            return $this->json(ECODE_DATABASE_QUERY_FAIL, []);
        }

        $list = $result['list'];
        $pagination = $result['pagination'];

        $responseData = [
            'list'    => $list,
            'pagination' => [
                'has_next_page'=> $pagination['has_next_page'],
                'next_cursor'  => $pagination['next_cursor'],
            ],

        ];

        return $this->json(ECODE_SUCCESS, $responseData);
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
