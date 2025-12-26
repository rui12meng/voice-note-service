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
     * getDetailBySql
     * @param  int  $uid
     * @param  int  $habitId
     * @return mixed
     */
    public function getDetailBySql($uid, $habitId){

        $sql = <<<SQL
SELECT
    h.note_id,
    h.habit_name,
    h.habit_desc,
    h.remind_time,
    h.status,
    hs.frequency_type,
    hs.frequency_config
FROM user_habits AS h
LEFT JOIN user_habit_schedules AS hs 
    ON h.id = hs.habit_id
WHERE h.id = {$habitId} 
    AND h.user_id = {$uid} 
    AND h.is_deleted = 0
LIMIT 1
SQL;

        $result = $this->query($sql);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

    /**
     * getListBySql
     * @param   int  $uid
     * @param   string  $keyword
     * @param   int  $cursor
     * @param   int $pageSize
     * @return mixed
     */
    public function getListBySql($uid, $keyword = '', $cursor = 0, $pageSize = 20){

        // 链表一次性查询习惯及对应规则，可按 habit_name/habit_desc/note_summary 全文索引搜索
        $keywordSql = '';
        if (!empty($keyword)) {
            $keywordSql = " and MATCH(h.habit_name, h.habit_desc, h.note_summary)
           AGAINST( '{$keyword}' IN NATURAL LANGUAGE MODE) ";
        }
        $cursorSql = '';
        if (!empty($cursor)) {
            $cursor = (int)$cursor;
            $cursorSql = " and h.id <= {$cursor} ";
        }

        $sql = <<<SQL
SELECT
    h.id,
    h.habit_name,
    h.remind_time,
    h.created_at,
    hs.frequency_type,
    hs.frequency_config
FROM user_habits AS h
JOIN user_habit_schedules AS hs
    ON hs.habit_id = h.id
WHERE h.user_id = {$uid} AND h.is_deleted = 0
  {$keywordSql}
  {$cursorSql}
ORDER BY h.id DESC
LIMIT {$pageSize}
SQL;

        $result = $this->query($sql);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }
}

