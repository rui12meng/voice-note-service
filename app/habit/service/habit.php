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
    public function addUserHabit($uid, $noteId, $habitName, $habitDesc = '', $remindTime, $active, $frequencyType, $frequencyConfig){
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
        $row = $this->_daoHabitsModel->getRowBySql($uid, $habitId);

        if (!$row) {
            return false;
        }

        // 解析频率配置
        if ($row['frequency_config']) {
            $row['frequency_config'] = json_decode($row['frequency_config'], true);
        }

        return $row;
    }

}
