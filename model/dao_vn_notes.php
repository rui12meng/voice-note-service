<?php
namespace Model;

/**
 * 用户笔记数据
 * @author mengrui
 * $Id: dao_vn_notes.php $
 */

class DaoVnNotes extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'notes';

    public function __construct()
    {
        parent::__construct();
    }

}