<?php
namespace Habit\Service;


/**
 * 习惯服务
 * @author mengrui
 * $Id: habit.php $
 */

class Habit
{
    private $_daoHabitsModel;
    private $_daoHabitSchedulesModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_daoHabitsModel = \Lsf\Loader::Model('DaoVnHabits', false, APP_NAME_NOTE);
        $this->_daoHabitSchedulesModel = \Lsf\Loader::Model('DaoVnHabitSchedules', false, APP_NAME_NOTE);
    }

    /**
     * 获取用户习惯总数
     * 每用户限制50个习惯
     * @param int    $uid 用户ID
     * @return int
     */
    public function countUserActiveHabits($uid){
        $where = [
            'user_id' => $uid,
            'is_deleted' => 0,
        ];
        $result = $this->_daoHabitsModel->count('id', $where);
        return $result;
    }

    /**
     * 添加用户习惯
     * 每用户限制50个习惯
     * @param int    $uid 用户ID
     * @param int    $noteId 笔记ID
     * @param string $habitName
     * @param string $habitDesc
     * @param string $remindTime
     * @param int    $active
     * @param string $frequencyType
     * @param array  $frequencyConfig
     * @return int
     */
    public function addUserHabit($uid, $noteId, $habitName, $habitDesc = '', $remindTime, $active, $frequencyType, $frequencyConfig): int
    {
        $result = $this->countUserActiveHabits($uid);
        if($result === false){
            return -7; //database
        }

        if ($result >= 50) {
            return -5;
        }

        $data = [
            'user_id' => $uid,
            'habit_name' => $habitName,
            'habit_desc' => $habitDesc,
            'remind_time' => $remindTime,
            'status' => (int)$active,
        ];
        if($noteId > 0){
            $data['note_id'] = $noteId;
        }
        $this->_daoHabitsModel->begin();
        $habitId = $this->_daoHabitsModel->insert($data);
        if($habitId === false){
            $this->_daoHabitsModel->rollback();
            return -7;
        }
        $habitRule = [
            'habit_id' => $habitId,
            'frequency_type' => $frequencyType,
            'frequency_config' => json_encode($frequencyConfig, JSON_UNESCAPED_UNICODE),
        ];
        $habitRuleId = $this->_daoHabitSchedulesModel->insert($habitRule);
        if($habitRuleId === false){
            $this->_daoHabitsModel->rollback();
            return -7;
        }
        $this->_daoHabitsModel->commit();
        return $habitId;
    }

    /**
     * 根据习惯ID获取详情
     * @param int    $uid 用户ID
     * @param int    $habitId 习惯ID
     * @return void
     */
    public function getUserHabitDetail($uid, $habitId){
        // 使用链表查询一次性取出习惯及对应规则
        $row = $this->_daoHabitsModel->getDetailBySql($uid, $habitId);

        if ($row === false) {
            return -7;
        }
        $result = [];
        if(isset($row[0])){
            $result = $row[0];
        }
        // 解析频率配置
        if ($result['frequency_config']) {
            $result['frequency_config'] = json_decode($result['frequency_config'], true);
        }

        // 若 note_id 存在，补充查询笔记标题
        if (!empty($result['note_id'])) {
            $daoNote = \Lsf\Loader::Model('DaoVnNotes', true);
            $note = $daoNote->find('title', $result['note_id']);
            if ($note !== false && isset($note['title'])) {
                $result['note_title'] = $note['title'];
            }
        }

        return $result;
    }

    /**
     * 根据习惯ID编辑习惯
     * @param int    $uid 用户ID
     * @param int    $habitId 习惯ID
     * @param string $habitName
     * @param string $habitDesc
     * @param string $remindTime
     * @param int    $active
     * @param string $frequencyType
     * @param array  $frequencyConfig
     * @return void
     */
    public function editUserHabit($uid, $habitId, $habitName, $habitDesc, $remindTime, $active, $frequencyType, $frequencyConfig): int
    {

        // 检查习惯是否存在且属于该用户
        $habit = $this->_daoHabitsModel->select('id', ['id' => $habitId, 'user_id' => $uid, 'is_deleted' => 0]);
        if($habit === false){
            return -7;
        }
        if (empty($habit)) {
            return -4; // 习惯不存在或无权限
        }

        //todo 补充逻辑[当$frequencyType = interval时，锚点数据需要算法更新]
        if(trim($frequencyType) === 'interval'){

            $habitSchedules = $this->_daoHabitSchedulesModel->select('frequency_type,frequency_config', ['habit_id' => $habitId]);
            if($habitSchedules === false){
                return -7;
            }
            //说明更新类型未变，仅改变规则间隔时间
            if(isset($habitSchedules[0]['frequency_type']) && $habitSchedules[0]['frequency_type'] === 'interval'){
                $config = json_decode($habitSchedules[0]['frequency_config'], true);
                $latestDate = $this->_getLastOccurrenceDate($config['anchor_date'], $config['days']);
                $frequencyConfig['anchor_date'] = $latestDate;
            }

        }


        $this->_daoHabitsModel->begin();

        // 更新习惯主表
        $updateData = [
            'habit_name'  => $habitName,
            'habit_desc'  => $habitDesc,
            'remind_time' => $remindTime,
            'status'      => (int)$active,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        $res = $this->_daoHabitsModel->update($updateData, ['id' => $habitId, 'user_id' => $uid]);
        if ($res === false) {
            $this->_daoHabitsModel->rollback();
            return -7; // 数据库错误
        }

        // 更新习惯规则表
        $ruleUpdate = [
            'frequency_type'   => $frequencyType,
            'frequency_config' => json_encode($frequencyConfig, JSON_UNESCAPED_UNICODE),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        $res = $this->_daoHabitSchedulesModel->update($ruleUpdate, ['habit_id' => $habitId]);
        if ($res === false) {
            $this->_daoHabitsModel->rollback();
            return -7; // 数据库错误
        }

        $this->_daoHabitsModel->commit();
        return $habitId;
    }

    /**
     * 软删除用户习惯
     * @param int $uid      用户ID
     * @param int $habitId  习惯ID
     * @return int 0:成功；-4:习惯不存在或已删除；-7:数据库错误
     */
    public function softDeleteUserHabit($uid, $habitId): int
    {
        // 检查习惯是否存在且属于该用户且未被删除
        $habit = $this->_daoHabitsModel->select('id', ['id' => $habitId, 'user_id' => $uid, 'is_deleted' => 0]);
        if($habit === false){
            return -7;
        }
        //若习惯不存在，静默忽略
        if (empty($habit)) {
            // 习惯不存在或已删除，不报错
            return 0;
        }

        $this->_daoHabitsModel->begin();
        $res = $this->_daoHabitsModel->update(['is_deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $habitId, 'user_id' => $uid]);
        if ($res === false) {
            $this->_daoHabitsModel->rollback();
            return -7;
        }
        $res = $this->_daoHabitSchedulesModel->update(['is_deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')], ['habit_id' => $habitId]);
        if ($res === false) {
            $this->_daoHabitsModel->rollback();
            return -7; // 数据库错误
        }

        $this->_daoHabitsModel->commit();
        return 0;
    }

    /**
     * 获取用户习惯列表
     * @param   int $uid 用户ID
     * @param   string  $keyword 搜索关键词
     * @param   int $cursor 游标
     * @param   int $pageSize limit数量
     * @return  array
     */
    public function getUserHabitList($uid, $keyword, $cursor, $pageSize): array
    {
        if ($pageSize > 50) {
            $pageSize = 50;
        }

        $list = $this->_daoHabitsModel->getListBySql($uid, $keyword, $cursor, $pageSize+1);

        if ($list === false) {
            return -7;
        }

        $hasNext = false;
        $nextCursor = 0;
        if (count($list) > $pageSize) {
            $hasNext = true;
            $lastItem = array_pop($list); // 移除多余的一条
            $nextCursor = $lastItem['id'];
        }

        // 解析频率配置
        foreach ($list as &$item) {
            if ($item['frequency_config']) {
                $item['frequency_config'] = json_decode($item['frequency_config'], true);
            }
            $item['next_execution_tip'] = $this->_getExecutionPrompt($item['frequency_type'], $item['frequency_config']);
        }
        unset($item);

        return [
            'list' => $list,
            'pagination' => [
                'has_next_page' => $hasNext,
                'next_cursor'   => $nextCursor
            ]
        ];
    }

    /**
     * 计算基于锚点和新间隔的“上一次（或当天）执行日期”
     * @param string $anchorDate  原始锚点日期，格式 'Y-m-d'
     * @param int    $interval       新的周期间隔（天）
     * @return string                返回 'Y-m-d' 格式的日期
     */
    private function _getLastOccurrenceDate(string $anchorDate, int $interval): string
    {
        // 当前日期（00:00:00）
        $today = new \DateTime('today');
        // 解析锚点日期
        $anchorDt = \DateTime::createFromFormat('Y-m-d', $anchorDate);
        if (!$anchorDt) {
            // 容错：解析失败则默认用今天
            return $today->format('Y-m-d');
        }
        $anchorDt->setTime(0, 0, 0);

        //计算今天与锚点的天数差（带符号）
        $diff = $today->diff($anchorDate);
        $diffDays = (int)$diff->format('%r%a'); // Signed days

        if ($diffDays > 0) { //锚点在未来
            return $anchorDate;
        } else { //锚点在过去 或 今天
            $passed = abs($diffDays); // 已经过了多少天（非负）
            $k = intdiv($passed, $interval);
            $lastTs = $anchorDt->getTimestamp() + ($k * $interval * 86400);
        }
        return $lastTs;
    }

    /**
     * 计算习惯下一次执行提示
     * @param string $type
     * @param array $config
     * @return string
     */
    private function _getExecutionPrompt($type, $config): string
    {
        if ($type === 'daily') {
            return '每天都执行';
        }

        $days = isset($config['days']) ? (int)$config['days'] : 0;
        if ($days <= 0 && $type !== 'interval') return '';

        $today = new \DateTime('today'); // Sets time to 00:00:00

        $daysUntil = null;

        if ($type === 'weekly') {
            // $days is 1-7 (Mon-Sun)
            $currentWeekDay = (int)$today->format('N'); //获取今天是星期几
            $diff = $days - $currentWeekDay;
            if ($diff < 0) {
                $diff += 7;
            }
            $daysUntil = $diff;
        } elseif ($type === 'monthly') {
            // $days is day of month（1～28/29/30/31）
            $currentDay = (int)$today->format('j');

            //未到本月执行日期
            if ($days >= $currentDay) {
                $daysUntil = $days - $currentDay;
            } else {
                // Next month
                $nextMonth = clone $today;
                $nextMonth->modify('first day of next month');//将日期设为 下个月的第一天
                $targetDate = clone $nextMonth;
                $year = (int)$nextMonth->format('Y');
                $month = (int)$nextMonth->format('m');
                //计算 下个月有多少天
                $daysInNextMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                //如果 $days（比如 31）大于下个月的天数（比如 28），就取下个月的最后一天（28）
                $targetDay = min($days, $daysInNextMonth);
                //将 $targetDate 设置为 下个月的目标日期
                $targetDate->setDate($year, $month, $targetDay);
                //计算 $targetDate 和 $today 之间的天数差
                $diff = $targetDate->diff($today)->days;
                $daysUntil = $diff;
            }
        } elseif ($type === 'interval') {
            // config['days'] = interval, config['date'] = anchor
            $interval = $days;
            $anchorStr = $config['anchor_date'] ?? $today->format('Y-m-d');
            $anchorDate = \DateTime::createFromFormat('Y-m-d', $anchorStr);
            if (!$anchorDate) $anchorDate = $today;
            $anchorDate->setTime(0, 0, 0);

            //计算今天与锚点的天数差（带符号）
            $diff = $today->diff($anchorDate);
            $diffDays = (int)$diff->format('%r%a'); // Signed days

            if ($diffDays > 0) { //锚点在未来
                $daysUntil = $diffDays;
            } else { //锚点在过去 或 今天
                $passed = abs($diffDays); // 已经过了多少天（非负）
                $mod = $passed % $interval;
                if ($mod == 0) {
                    $daysUntil = 0;
                } else {
                    $daysUntil = $interval - $mod;
                }
            }
        }

        if ($daysUntil === 0) {
            return '今天执行';
        } elseif ($daysUntil > 0) {
            return $daysUntil . '天后执行';
        }

        return '';
    }

}
