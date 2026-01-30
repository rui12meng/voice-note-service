<?php
namespace Action\Model;

/**
 * 行动复盘管理
 * @author mengrui
 */
class DaoVnUserReflections extends \Lsf\Model
{
    public $primary = 'id';
    public $tablePrefix = '';
    public $table = 'user_reflections';

    public function __construct()
    {
        parent::__construct();
    }
}

