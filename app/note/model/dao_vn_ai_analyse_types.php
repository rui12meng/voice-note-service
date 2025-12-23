<?php
namespace Note\Model;

class DaoVnAiAnalyseTypes extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'ai_analyse_types';

    public function __construct(){
        parent::__construct();
    }
}

