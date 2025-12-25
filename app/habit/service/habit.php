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

    public function editUserHabit($uid, $habitId, $habitName, $habitDesc, $remindTime, $active, $frequencyType, $frequencyConfig){

        // 检查习惯是否存在且属于该用户
        $habit = $this->_daoHabitsModel->select('id', ['id' => $habitId, 'user_id' => $uid, 'is_deleted' => 0]);
        if($habit === false){
            return -7;
        }
        if (empty($habit)) {
            return -4; // 习惯不存在或无权限
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
}
