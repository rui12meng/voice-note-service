<?php
namespace Model;

/**
 * 用户行动数据
 * @author mengrui
 * $Id: dao_vn_actions.php $
 */

class DaoVnActions extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'user_actions';

    public function __construct()
    {
        parent::__construct();
    }

}