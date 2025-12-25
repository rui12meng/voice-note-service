<?php
namespace Habit\Model;

/**
 * 用户习惯管理
 * @author mengrui
 */
class DaoVnHabits extends \Lsf\Model
{
    public $primary = 'id';
    public $tablePrefix = '';
    public $table = 'user_habits';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * getRowBySql
     * @param  int  $uid
     * @param  int  $habitId
     * @return mixed
     */
    public function getRowBySql($uid, $habitId){

        $sql = "SELECT h.note_id, h.habit_name, h.habit_desc, h.remind_time, h.status, hs.frequency_type, hs.frequency_config
                FROM user_habits AS h
                LEFT JOIN user_habit_schedules AS hs ON h.id = hs.habit_id
                WHERE h.id = {$habitId} AND h.user_id = {$uid} AND h.is_deleted = 0
                LIMIT 1";

        $result = $this->query($sql);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }
}

