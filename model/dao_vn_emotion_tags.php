<?php
namespace Model;

/**
 * 情绪标签数据
 * @author mengrui
 * $Id: dao_vn_emotion_tags.php $
 */

class DaoVnEmotionTags extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'emotion_tags';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 按条件查询情绪标签
     * @param  int    $uid
     * @param  int    $days
     * @return void
     */
    public function getEmotionTagsBySql($uid, $days = -7){
        $startDate = date('Y-m-d H:i:s', strtotime("{$days} days"));
        $sql = <<<SQL
SELECT
    et.emotion_type_id,
    COUNT(*) AS emotion_count,
    ROUND(SUM(et.intensity) / COUNT(*), 2) AS emotion_star
FROM emotion_tags AS et
JOIN notes AS n 
    ON et.note_id = n.id
WHERE n.user_id = {$uid} 
    AND n.create_at >= "{$startDate}" 
    AND et.is_deleted = 0
GROUP BY 
    et.emotion_type_id
ORDER BY 
    emotion_count DESC, emotion_star DESC  -- 先按频次降序，再按强度降序
LIMIT 3
SQL;

        $result = $this->query($sql);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

}