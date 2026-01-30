<?php
namespace Action\Model;

/**
 * 行动复盘AI分析管理
 * @author mengrui
 */
class DaoVnReflectionAiAnalysis extends \Lsf\Model
{
    public $primary = 'id';
    public $tablePrefix = '';
    public $table = 'reflection_ai_analysis';

    public function __construct()
    {
        parent::__construct();
    }
}

