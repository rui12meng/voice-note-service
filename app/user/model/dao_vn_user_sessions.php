<?php
namespace User\Model;

/**
 * 用户数据
 * @author mengrui
 * $Id: dao_vn_user_sessions.php $
 */

class DaoVnUserSessions extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'user_sessions';

    /**
     * 构造函数
     * @param void
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 存储会话信息
     * @param  array   $data
     * @return mixed
     */
    public function storeData($data){
        $result = $this->insert($data);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result; //主键id
        }
    }

    /**
     * 更新会话信息
     * @param  array   $data
     * @param  array   $where
     * @return mixed
     */
    public function updateSession($data, $where){
        $result = $this->update($data, $where);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }



}