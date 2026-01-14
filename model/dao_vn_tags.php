<?php
namespace Model;

/**
 * 笔记标签数据
 * @author mengrui
 * $Id: dao_vn_tags.php $
 */

class DaoVnTags extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'note_tags';

    public function __construct()
    {
        parent::__construct();
    }

}