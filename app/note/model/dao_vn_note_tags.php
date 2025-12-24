<?php
namespace Note\Model;

/**
 * note标签管理
 * @author mengrui
 */
class DaoVnNoteTags extends \Lsf\Model
{
    public $primary = 'id';
    public $tablePrefix = '';
    public $table = 'note_tags';

    public function __construct()
    {
        parent::__construct();
    }
}

