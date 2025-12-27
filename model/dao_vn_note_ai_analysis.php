<?php
namespace Model;

/**
 * 用户日记分析数据
 * @author mengrui
 * $Id: dao_vn_note_ai_analysis.php $
 */

class DaoVnNoteAiAnalysis extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'note_ai_analysis';

    public function __construct()
    {
        parent::__construct();
    }

}