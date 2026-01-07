<?php
namespace Model;

/**
 * 投诉详情
 * @author mengrui
 * $Id: DaoVnUserSessions.php $
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

    /**
     * 更新会话唯一标识查询会话信息
     * @param  string   $columns
     * @param  string   $jti
     * @param  int      $limit
     * @return mixed
     */
    public function findSessionByJti($jti, $columns = '*', $limit = 1){
        $where = ['jti' => $jti];
        $result = $this->select($columns, $where, $this->primary . ' DESC', $limit);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

    /**
     * 根据会话唯一标识更新会话状态
     * @param  string   $jti
     * @return mixed
     */
    public function changeSessionStatusByJti($jti){
        $data = [
            'status' => 2,
        ];
        $where = [
            'jti' => $jti,
        ];
        $result = $this->update($data, $where);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }
}