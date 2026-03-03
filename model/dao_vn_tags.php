<?php
namespace Model;

/**
 * 笔记标签数据
 * @author mengrui
 * $Id: dao_vn_tags.php $
 */

class DaoVnTags extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'note_tags';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 按条件查询日记标签
     * @param  int    $uid
     * @param  int    $days
     * @return void
     */
    public function getNoteTagsBySql($uid, $days){
        $startDate = date('Y-m-d H:i:s', strtotime("{$days} days"));

        //todo 如果标签数据量很大，可能会出现性能瓶颈，避免性能开销，限制200
        $sql = <<<SQL
SELECT
    nt.note_id, 
    nt.name
FROM note_tags AS nt
JOIN notes AS n 
    ON nt.note_id = n.id
WHERE n.user_id = {$uid} 
    AND n.created_at >= "{$startDate}" 
    AND nt.is_deleted = 0
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