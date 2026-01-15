<?php
namespace Model;

/**
 * 用户习惯数据
 * @author mengrui
 * $Id: dao_vn_habits.php $
 */

class DaoVnHabits extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'user_habits';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取某日需要执行的习惯列表
     * todo 习惯规则变更时，重置 streak_count = 0；因为“每2天跑一次”和“每3天跑一次”是两个不同的行为目标，连续性不应继承
     * todo current_streak COMMENT '写时维护，仅在 last_done_date 连续时可信'
     * todo 一旦规则变了，必须重算 current_streak = 0
     * @param int $uid 用户ID
     * @param string $execTime 执行日期（Y-m-d）
     * @param int $limit
     * @return array 某日期 需要执行的习惯列表
     */
    public function getExecHabitsList($uid, $execTime, $limit = 50)
    {


$sql = <<<SQL
SELECT
            id,
            note_id,
            habit_name,
        CASE
            WHEN last_done_date IS NULL THEN 0
            WHEN DATEDIFF({$execTime}, last_done_date) = (CASE interval_unit
                WHEN 'day'   THEN interval_num
                WHEN 'week'  THEN interval_num * 7
                WHEN 'month' THEN interval_num * 30
            END)THEN current_streak
            ELSE 0
        END AS streak
        FROM user_habits
        WHERE user_id = {$uid}
        AND status = 1
        AND is_deleted = 0
        AND DATEDIFF('{$execTime}', anchor_date) >= 0
        AND MOD(DATEDIFF('{$execTime}', anchor_date), 
        CASE interval_unit
            WHEN 'day'   THEN interval_num
            WHEN 'week'  THEN interval_num * 7
            WHEN 'month' THEN interval_num * 30
        END
        ) = 0
        LIMIT {$limit};
SQL;

        // todo 链表查询 后期版本
        /*$sql = <<<SQL
SELECT
    h.id,
    h.note_id,
    h.habit_name,
    h.streak_count,
    h.last_done_date,
    hs.frequency_type,
    hs.frequency_config
FROM user_habits AS h
LEFT JOIN user_habit_schedules AS hs
    ON h.id = hs.habit_id
WHERE h.user_id = {$uid}
    AND h.status = 1
    AND h.is_deleted = 0
LIMIT {$limit}
SQL;
*/
        $habits = $this->query($sql);

        if($habits === FALSE){
            return FALSE;
        }else{
            return $habits;
        }

    }

    public function editHabitsByStreak($uid, $actionId, $habitId, $execDate, $status){

        $sql = <<<SQL
UPDATE user_habits
SET
  last_done_date = '{$execDate}',

  streak_count = streak_count + 1,

  current_streak = CASE
    WHEN last_done_date IS NULL
      THEN 1
    WHEN DATEDIFF('{$execDate}', last_done_date) = (
    CASE interval_unit
            WHEN 'day'   THEN interval_num
            WHEN 'week'  THEN interval_num * 7
            WHEN 'month' THEN interval_num * 30
        END
    )
      THEN current_streak + 1
    ELSE
      1
  END
WHERE id = {$habitId};

SQL;

        $this->begin();
        $result = $this->query($sql);
        if($result === false){
            $this->rollback();
            return -7;
        }

        $data = ['status' => (int)$status , 'streak' => ['exp', 'streak + 1']];
        $where = [
            'id' => $actionId,
            'user_id' => $uid,
        ];
        $_daoVnActionsModel =\Lsf\Loader::Model('DaoVnActions',true);
        $result = $_daoVnActionsModel->update($data, $where);

        if($result === false){
            $this->rollback();
            return -7;
        }
        $this->commit();
        return $result;

    }

    /**
     * 获取用户笔记关联的习惯（仅一个）
     * @param int $uid 用户ID
     * @param int $noteId 笔记ID
     * @return array
     */
    public function getHabitsByNoteId($uid, $noteId){
        $where = [
            'user_id' => $uid,
            'note_id' => $noteId,
            'is_deleted' => 0,
        ];
        $columns = 'id, habit_name, habit_desc, interval_num, interval_unit, status';
        $result = $this->select($columns , $where, '', $limit = 1);
        if($result === false){
            return [];
        }
        return $result;
    }

}



