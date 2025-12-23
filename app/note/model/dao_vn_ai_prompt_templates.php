<?php
namespace Note\Model;

class DaoVnAiPromptTemplates extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'ai_prompt_templates';

    public function __construct(){
        parent::__construct();
    }
}

