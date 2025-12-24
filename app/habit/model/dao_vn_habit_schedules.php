<?php
namespace Habit\Model;

/**
 * 用户习惯规则管理
 * @author mengrui
 */
class DaoVnHabitSchedules extends \Lsf\Model
{
    public $primary = 'id';
    public $tablePrefix = '';
    public $table = 'user_habit_schedules';

    public function __construct()
    {
        parent::__construct();
    }


}

