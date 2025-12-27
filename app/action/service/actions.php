<?php
namespace Action\Service;


/**
 * 日记行动服务
 * @author mengrui
 * $Id: actions.php $
 */

class Actions
{
    private $_daoVnActionsModel;
    private $_daoVnNoteAiAnalyzeModel;
    private $_daoVnNotesModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_daoVnActionsModel = \Lsf\Loader::Model('DaoVnActions');
        $this->_daoVnNoteAiAnalyzeModel = \Lsf\Loader::Model('DaoVnNoteAiAnalysis', true);
        $this->_daoVnNotesModel = \Lsf\Loader::Model('DaoVnNotes', true);
    }

    /**
     * 根据笔记id删除笔记下关联的全部行动
     * @param  int $uid    用户ID
     * @param  int $noteId 日记ID
     * @param  string $structType  模块类型
     * @return void
     */
    public function delActionsByNoteId($uid, $noteId, $structType)
    {
        //先查询日记是否属于该用户
        $notes = $this->_daoVnNotesModel->select('id', ['id' =>$noteId , 'user_id' => $uid, 'is_deleted' => 0 ]);
        if($notes === false){
            return -7;
        }
        if(empty($notes)){ //数据为空或无权限或日记已删除
            return -6;
        }

        $data = ['is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s')];
        $where = [
            'note_id' => $noteId,
            'analysis_type_name' => $structType,
        ];
        $this->_daoVnNoteAiAnalyzeModel->begin();
        $result = $this->_daoVnNoteAiAnalyzeModel->update($data, $where);
        if($result === false){
            $this->_daoVnNoteAiAnalyzeModel->rollback();
            return -7;
        }
        $rows = $this->_daoVnActionsModel->softDelete(['is_deleted' => 1], ['note_id' => $noteId,'user_id' => $uid]);
        if($rows === false){
            $this->_daoVnNoteAiAnalyzeModel->rollback();
            return -7;
        }
        $this->_daoVnNoteAiAnalyzeModel->commit();
        if(is_int($rows) && ($rows >= 0)){
            // 静默忽略数据不存在的情况，统一返回1
            return 1;
        }else{
            return -5; //未知错误
        }
    }

}