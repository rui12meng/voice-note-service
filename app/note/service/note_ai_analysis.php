<?php
namespace Note\Service;

use PhpParser\Node\Stmt\Switch_;

/**
 * AI分析服务
 * @author mengrui
 */
class NoteAiAnalysis
{
    private $_daoVnNoteAiAnalysis;
    private $_daoVnActionsModel;
    private $_daoVnHabitsModel;

    public function __construct()
    {
        $this->_daoVnNoteAiAnalysis = \Lsf\Loader::model('DaoVnNoteAiAnalysis', false, APP_NAME_NOTE);
        $this->_daoVnActionsModel = \Lsf\Loader::model('DaoVnActions', true);
        $this->_daoVnHabitsModel = \Lsf\Loader::model('DaoVnHabits', true);
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

    /**
     * (使用场景：用户删除笔记，关联的分析数据全部删除)
     * 软删除笔记AI分析记录
     * @param int $noteId 笔记ID
     * @return int 影响行数
     */
    public function softDeleteByNoteId($noteId)
    {
        $result = $this->_daoVnNoteAiAnalysis->softDelete(
            ['is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s')],
            ['note_id' => $noteId]
        );

        return $result;
    }

    /**
     * 软删除笔记AI分析模块
     * @param int $noteId 笔记ID
     * @param string $structType 模块名
     * @return int 影响行数
     */
    public function delAiStructData($noteId, $structType){
        if($structType === 'actions'){ //行动项单独处理
            $data = ['is_deleted' => 1];
            $where = ['note_id' => $noteId];
            $result = $this->_daoVnActionsModel->softDelete($data, $where);
            if($result === false){
                return -7;
            }
            if(is_int($result) && ($result >= 0)){
                return 1;
            }else{
                return -6;
            }
        }else{
            $data = ['is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s')];
            $where = [
                'note_id' => $noteId,
                'analysis_type_name' => $structType,
            ];
            $result = $this->_daoVnNoteAiAnalysis->softDelete($data, $where);

            $resultHabit = null;
            if($structType === 'habits'){
                $data = ['is_deleted' => 1];
                $where = ['note_id' => $noteId];
                $resultHabit = $this->_daoVnHabitsModel->softDelete($data, $where);
            }
            if($result === false || ($structType === 'habits' && $resultHabit === false)){
                return -7;
            }
            if(!is_int($result) || $result < 0){
                return -6;
            }
            if($structType === 'habits' && (!is_int($resultHabit) || $resultHabit < 0)){
                return -6;
            }
            return 1;
        }

    }

    /**
     * 更新笔记AI分析模块数据
     * @param int $noteId 笔记ID
     * @param string $structType 模块名
     * @param mixed $data 新的分析数据
     * @return int 影响行数
     */
    public function getNewAiStructData($noteId, $structType, $data){
        $result = $this->updateAiStructData($noteId, $structType, $data);
        if ($result === false) {
            return -7;
        }
        if (is_int($result) && ($result == 1 || $result == 0)) {
            $columns = 'note_id, analysis_data, updated_at';
            $where = [
                'note_id' => $noteId,
                'analysis_type_name' => $structType,
                'is_deleted' => 0,
            ];
            $noteAnal = $this->_daoVnNoteAiAnalysis->select($columns, $where);
            if($noteAnal === false){
                return -7;
            }
            if(isset($noteAnal[0])){
                return $noteAnal[0];
            }else{
                return [];
            }
        } else {
            return -6;
        }
    }

    /**
     * 更新笔记AI分析模块数据
     * @param int $noteId 笔记ID
     * @param string $structType 模块名
     * @param mixed $data 新的分析数据
     * @return int 影响行数
     */
    private function updateAiStructData($noteId, $structType, $data)
    {
        $updateData = [
            'analysis_data' => is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE),
        ];
        $where = [
            'note_id' => $noteId,
            'analysis_type_name' => $structType,
            'is_deleted' => 0,
        ];
        $result = $this->_daoVnNoteAiAnalysis->update($updateData, $where);

        return $result;
    }
}
