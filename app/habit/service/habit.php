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
     * 没用户限制50个习惯
     * @param int    $uid 用户ID
     * @param int    $noteId 笔记ID
     * @param string $habitName
     * @param int    $intervalNum
     * @param string $intervalUnit
     * @return int
     */
    public function addUserHabit($uid, $noteId, $habitName, $intervalNum, $intervalUnit){
        $result = $this->countUserActiveHabits($uid);
        if($result === false){
            return -7; //database
        }

        if ($result >= 50) {
            return -5;
            //$this->error('您已拥有50个启用状态的习惯，已达上限，请先停用或删除部分习惯后再添加');
        }

        $data = [
            'user_id' => $uid,
            'habit_name' => $habitName,
        ];
        if($noteId > 0){
            array_push($data, ['note_id' => $noteId]);
        }
        $habitId = $this->_daoHabitsModel->insert($data);
        if($habitId === false){
            return -7; //database
        }

        $habitRule = [
            'habit_id' => $habitId,
            'interval_value' => $intervalNum,
            'interval_unit' => $intervalUnit,
            'anchor_date' => date('Y-m-d'),
        ];
        $habitRuleId = $this->_daoHabitSchedulesModel->insert($habitRule);
        if($habitRuleId === false){
            return -7; //database
        }
        return $habitId;
    }

}