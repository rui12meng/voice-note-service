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
}