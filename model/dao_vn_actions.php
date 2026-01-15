<?php
namespace Model;

/**
 * 用户行动数据
 * @author mengrui
 * $Id: dao_vn_actions.php $
 */

class DaoVnActions extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'user_actions';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取用户笔记关联的行动list
     * @param   int     $uid
     * @param   int     $noteId
     * @return void
     */
    public function getActions($uid, $noteId){
        $where = [
            'user_id' => $uid,
            'note_id' => $noteId,
            'is_deleted' => 0,
        ];
        $columns = 'id, title, content, status, due_date ';
        $order = 'id asc';
        $result = $this->select($columns, $where, $order);

        if($result === FALSE){
            return [];
        }
        return $result;
    }

}