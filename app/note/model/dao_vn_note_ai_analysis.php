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

    /**
     * 获取AI分析结果（洞察&情绪）
     * @param int $noteId
     * @return @void
     */
    public function getAiDataBySql($noteId){

        $sql = <<<SQL
SELECT
    id,
    analysis_type_name,
    analysis_data
FROM note_ai_analysis
WHERE note_id = {$noteId} 
    AND is_deleted = 0
    AND analysis_type_name IN ('insight', 'emotion', 'habits')
SQL;

        $result = $this->query($sql);

        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }

    }
}
