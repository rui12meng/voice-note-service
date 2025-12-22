<?php
namespace Note\Service;

/**
 * AI分析服务
 * @author mengrui
 */
class NoteAiAnalysis
{
    private $_daoVnNoteAiAnalysis;

    public function __construct()
    {
        $this->_daoVnNoteAiAnalysis = \Lsf\Loader::model('DaoVnNoteAiAnalysis', false, APP_NAME_NOTE);
    }

    /**
     * 添加AI分析记录
     * @param int $noteId
     * @param string $title
     * @param string $summary
     * @param string $analyzedAt
     * @return mixed
     */
    public function addNoteAiAnalysis($noteId, $title, $summary, $analyzedAt)
    {
        $data = [
            'note_id' => $noteId,
            'title' => $title,
            'summary' => $summary,
            'analyzed_at' => $analyzedAt,
        ];
        return $this->_daoVnNoteAiAnalysis->insert($data);
    }
}
