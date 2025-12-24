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

}

