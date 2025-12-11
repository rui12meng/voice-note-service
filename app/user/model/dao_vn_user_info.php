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
        var_dump($result);exit();
        if($result === FALSE){
            return FALSE;
        }else{
            return $result; //主键id
        }
    }



}