<?php
namespace Model;

/**
 * 记录某用户在某一天的习惯是否已同步到任务表
 * @author mengrui
 * $Id: dao_vn_habit_sync_records.php $
 */

class DaoVnHabitSyncRecords extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'habit_sync_records';

    public function __construct()
    {
        parent::__construct();
    }

}