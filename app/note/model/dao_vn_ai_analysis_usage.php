<?php
namespace Note\Model;

/**
 * notes笔记-AI分析调用资源消耗记录
 * @author mengrui
 * $Id: dao_vn_ai_analysis_usage.php$
 */

class DaoVnAiAnalysisUsage extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'ai_analysis_usage';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        parent::__construct();
    }
}