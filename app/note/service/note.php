<?php
namespace Note\Service;

/**
 * 笔记 service
 * $Id: note.php $
 * @author mengrui
 */
class Note
{
    const NOTE_TYPE_AUDIO   = 1;
    const NOTE_TYPE_TEXT    = 2;
    const NOTE_TYPE_IMAGE   = 3;
    /**
     * @var mixed
     */
    private $_daoVnNoteModel;

    /**
     * 构造函数
     * @param  void
     * @throws \Exception
     * @return void
     */
    public function __construct()
    {
        $this->_daoVnNoteModel  = \Lsf\Loader::model('DaoVnNote', true);
    }

    /**
     * 指令拍通知轮播
     * @param   int     $uid
     * @param   string  $noteText
     * @param   array  $audioUrls
     * @return void
     */
    public function addAudioNote($uid, $noteText, $audioUrls)
    {
        $data = [
            'user_id' => $uid,
            'note_type' => self::NOTE_TYPE_AUDIO,
            'context' => $noteText,
            'media_url' => json_encode($audioUrls),
            'created_at' => date("Y-m-d H:i:s"),
        ];

        $result = $this->_daoVnNoteModel->insert($data);

        if ($result === false) {
            //入库失败
            return -7;
        } else {
            return $result;
        }
    }

    /**
     * 更新笔记记录（AI分析数据）
     * @param   int     $noteId
     * @param   string  $title
     * @param   string  $summary
     * @param   string  $analyzed_at
     * @param   int     $moderation_status
     * @return void
     */
    public function updateNoteAiData($noteId, $title, $summary, $analyzed_at, $moderation_status){

        $data = [
            'title' => $title,
            'summary' => $summary,
            'analyzed_at' => $analyzed_at,
            'moderation_status' => $moderation_status,
            'is_analyzed' => 1,
        ];
        $where = [
            'id' => $noteId,
        ];
        $result = $this->_daoVnNoteModel->update($data, $where);

        if ($result === false) {
            //操作失败
            return -6;
        } else {
            return $result;
        }
    }

}
