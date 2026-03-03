<?php
namespace Model;

/**
 * 情绪标签数据
 * @author mengrui
 * $Id: dao_vn_emotion_tags.php $
 */

class DaoVnTags extends \Lsf\Model
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
    public function getEmotionTagsBySql($uid, $days){
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $sql = <<<SQL
SELECT
    et.note_id, 
    et.emotion_type_id,
    et.intensity
FROM emotion_tags AS et
JOIN notes AS n 
    ON et.note_id = n.id
WHERE n.user_id = {$uid} 
    AND n.create_at >= {$startDate} 
    AND et.is_deleted = 0
ORDER BY n.create_at DESC 
LIMIT 200
SQL;

        $result = $this->query($sql);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }


    }

}