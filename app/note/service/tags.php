<?php
namespace Note\Service;


/**
 * 日记标签服务
 * @author mengrui
 * $Id: tags.php $
 */

class Tags
{
    private $_daoNoteTagsModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_daoNoteTagsModel = \Lsf\Loader::Model('DaoVnNoteTags', false, APP_NAME_NOTE);
    }

    /**
     * 添加标签
     * @param  array    $data
     * @return void
     */
    public function addTag($data){

        //todo 缺失标签 用户添加TAG入库前，服务端需要根据TAG体系，给用户自定义的TAG归类 逻辑
        $result = $this->_daoNoteTagsModel->insert($data);
        if($result === false){
            return -7;
        }
        return $result;
    }

    /**
     * 删除标签
     * @param  int $uid    用户ID
     * @param  int $noteId 日记ID
     * @param  int $tagId  标签ID
     * @return int         删除成功返回1，标签不存在也返回1
     */
    public function deleteTag($uid, $noteId, $tagId)
    {
        $where = [
            'uid'    => $uid,
            'note_id'=> $noteId,
            'tag_id' => $tagId
        ];
        $result = $this->_daoNoteTagsModel->delete($where);
        var_dump($result);exit();
        // 静默忽略标签不存在的情况，统一返回1
        return 1;
    }

}