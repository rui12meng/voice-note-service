<?php
namespace Note\Model;

class DaoVnAiAnalysisTypes extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'ai_analysis_types';

    public function __construct(){
        parent::__construct();
    }
}

