<?php
namespace User\Model;

/**
 * 用户信息（资料）数据
 * @author mengrui
 * $Id: dao_vn_user_info.php $
 */

class DaoVnUserInfo extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'user_info';

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
     * 查询用户个人信息
     * @param  string   $col
     * @param  int   $uid
     * @param  int   $limit
     * @return mixed
     */
    public function findUserInfo($col,$uid,$limit=1)
    {
        $where['user_id'] = $uid;
        $result = $this->select($col, $where, $this->primary . ' DESC', $limit);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result; //主键id
        }
    }

    /**
     * 编辑用户个人信息
     * @param  int   $uid
     * @param  string   $user_info
     * @return mixed
     */
    public function editUserInfo($uid, $user_info){
        $where = [
            'user_id' => $uid,
        ];
        $result = $this->update($user_info, $where);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

    /**
     * 更新userInfo信息
     * @param  array   $data
     * @param  array   $where
     * @return mixed
     */
    public function updateUserInfo($data, $where){
        $result = $this->update($data, $where);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

}