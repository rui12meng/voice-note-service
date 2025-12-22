<?php
namespace Note\Model;

/**
 * AI分析结果记录表
 * @author mengrui
 */
class DaoVnNoteAiAnalysis extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'note_ai_analysis';

    public function __construct(){
        parent::__construct();
    }
}
