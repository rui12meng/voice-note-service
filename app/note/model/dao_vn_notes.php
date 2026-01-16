<?php
namespace Note\Model;

/**
 * notes笔记
 * @author mengrui
 * $Id: dao_vn_notes.php$
 */

class DaoVnNotes extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'notes';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        parent::__construct();
    }

    /**
     * 更新笔记
     * @param   int     $noteId
     * @param   string  $title
     * @param   string  $summary
     * @param   string  $analyzedAt
     * @param   int     $status
     * @param   int     $isAnalyzed
     * @return void
     */
    public function updateNoteAiData($noteId , $title, $summary, $analyzedAt,$status, $isAnalyzed = 0){
        $data = [
            'title' => $title,
            'summary' => $summary,
            'status' => $status,
            'is_analyzed' => $isAnalyzed,
            'analyzed_at' => $analyzedAt,
            'moderation_status' => $status,
        ];
        $where = [
            'id' => $noteId,
        ];
       $result = $this->update($data, $where);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

    /**
     * 关键字过滤列表
     * @param   int     $uid
     * @param   int     $cursor
     * @param   int     $pageSize
     * @param   string  $keyword
     * @return void
     */
    public function getUserNoteListByCursorWithKeyword($uid, $cursor, $pageSize, $keyword){
        $uid = (int)$uid;
        $cursor = (int)$cursor;
        $limit = (int)$pageSize + 1;
        $columns = 'id,title,summary,note_type,media_url,is_analyzed,analyzed_at,created_at';
        
        $kw = trim((string)$keyword);
        if ($kw === '') {
            return [];
        }
        $tokens = preg_split('/\s+/u', $kw, -1, PREG_SPLIT_NO_EMPTY);
        $parts = [];
        foreach ($tokens as $t) {
            $t = preg_replace('/[^\p{L}\p{N}_\-]+/u', '', $t);
            if ($t === '') {
                continue;
            }
            $parts[] = '+' . addslashes($t) . '*';
        }
        $booleanQuery = implode(' ', $parts);
        if ($booleanQuery === '') {
            return [];
        }
        
        $where = "user_id = {$uid} AND is_deleted = 0";
        if ($cursor > 0) {
            $where .= " AND id <= {$cursor}";
        }
        $where .= " AND MATCH(title) AGAINST('{$booleanQuery}' IN BOOLEAN MODE)";
        
        $sql = "SELECT {$columns} FROM {$this->table} WHERE {$where} ORDER BY id DESC LIMIT {$limit}";
        $result = $this->query($sql);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }
}
