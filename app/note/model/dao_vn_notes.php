<?php
namespace Note\Model;

/**
 * notes笔记
 * @author mengrui
 * $Id: dao_vn_notes.php$
 */

class DaoVnNotes extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'notes';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        parent::__construct();
    }
}