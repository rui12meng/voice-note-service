<?php
namespace Home\Service;


/**
 * 通用服务
 * @author mengrui
 * $Id: index.php $
 */

class Index
{
    private $_daoVnNotesModel;
    private $_daoVnNoteTagsModel;
    //private $_daoVnNoteEmotionTagsModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_daoVnNotesModel = \Lsf\Loader::Model('DaoVnNotes', true);
        $this->_daoVnNoteTagsModel = \Lsf\Loader::Model('DaoVnTags', true);
        //$this->_daoVnNoteEmotionTagsModel = \Lsf\Loader::Model('DaoVnEmotionTags', true);
    }

    /**
     * 按条件查询日记数量
     * @param  int    $uid
     * @param  int    $days
     * @return void
     */
    public function noteCountLast7Days($uid, $days){
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $columns = 'id';
        $where = [
            'user_id' => $uid,
            'is_analyzed' => 1,
            'is_deleted' => 0,
            'created_at' => ['EGT', $startDate], //>=
        ];
        $result = $this->_daoNoteTagsModel->count($columns, $where);
        if($result === false){
            return -7;
        }
        return $result;
    }

    /**
     * 获取窗口内的所有日记有效标签 (注意过滤 is_deleted=0)
     * @param  int $uid    用户ID
     * @param  int $days   时间窗口
     * @return void
     */
    public function userNoteTags($uid, $days)
    {
        $result = $this->_daoVnNoteTagsModel->getNoteTagsBySql($uid, $days);
        if(isset($result) && !empty($result)){
            // 去重操作：保证每个 note_id 和 name 组合只出现一次
            $uniqueTags = [];
            foreach ($result as $row) {
                // 使用 [note_id, name] 组合键来去重
                $uniqueTags[$row['note_id']][$row['name']] = true;
            }

            // 统计每个标签出现的次数
            $tagCounts = [];
            foreach ($uniqueTags as $noteId => $tags) {
                foreach ($tags as $tagName => $v) {
                    // 如果标签已经在统计数组中，增加计数
                    if (isset($tagCounts[$tagName])) {
                        $tagCounts[$tagName]++;
                    } else {
                        // 否则初始化计数
                        $tagCounts[$tagName] = 1;
                    }
                }
            }

            // 按出现次数排序并获取 Top 3 标签
            arsort($tagCounts);
            $topTags = array_slice($tagCounts, 0, 3);
            return $topTags;
        }else{
            return [];
        }

    }

    /**
     * 获取窗口内的所有日记情绪
     * @param  int $uid    用户ID
     * @param  int $days   时间窗口
     * @return void
     */
    public function userEmotionTags($uid, $days){
        //$this->_daoVnNoteEmotionTagsModel->getEmotionTagsBySql($uid, $days);
    }

}