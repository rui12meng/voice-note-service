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

    public function addNoteAiAnalysis($noteId, $analyzedAt)
    {
        $data = [
            'note_id' => $noteId,
            'analyzed_at' => $analyzedAt,
        ];
        return $this->_daoVnNoteAiAnalysis->insert($data);
    }

    public function addBatchNoteAiAnalysis($noteId, $model, array $items, $analyzedAt)
    {
        $rows = [];
        $now = date('Y-m-d H:i:s');
        foreach ($items as $k => $v) {
            $rows[] = [
                'note_id' => $noteId,
                'ai_model_version' => $model,
                'analysis_type_name' => $k,
                'analysis_data' => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE),
                'analyzed_at' => $analyzedAt,
                'created_at' => $now,
            ];
        }
        return $this->_daoVnNoteAiAnalysis->batchInsert($rows);
    }
}
