<?php
namespace Action\Model;

/**
 * 行动复盘管理
 * @author mengrui
 */
class DaoVnUserReflections extends \Lsf\Model
{
    public $primary = 'id';
    public $tablePrefix = '';
    public $table = 'user_reflections';

    public function __construct()
    {
        parent::__construct();
    }

    public function reflectionDetail($uid, $reflectionId){
        $sql = <<<SQL
SELECT
    r.start_date,
    r.end_date,
    raa.analysis,
    raa.action_suggestions
FROM user_reflections AS r
LEFT JOIN reflection_ai_analysis AS raa 
    ON r.id = raa.reflection_id
WHERE r.id = {$reflectionId} 
    AND r.user_id = {$uid} 
    AND r.is_deleted = 0
LIMIT 1
SQL;

        $result = $this->query($sql);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }
}

