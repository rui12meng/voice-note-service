<?php
namespace User\Model;

/**
 * 用户数据
 * @author mengrui
 * $Id: dao_user.php $
 */

class DaoUser extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'users';
    private $_svrDaoVnUserAuthModel;
    private $_svrDaoVnUserInfoModel;


    /**
     * 根据授权服务商及授权uid获取用户信息
     * @param  string  $columns
     * @param  int     $productId
     * @param  string  $configSign
     * @param  int     $limit
     * @return mixed
     */
    public function query($columns = '*',   $where = [], $limit = 1){
        $where = [];
        $result = $this->select($columns, $where, $this->primary . ' DESC', $limit);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

    /**
     * 注册user信息
     * @param  array   $data
     * @return mixed
     */
    public function storeData($data){

        $result = $this->insert($data);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }

    }

    /**
     * 更新user信息
     * @param  array   $data
     * @param  array   $where
     * @return mixed
     */
    public function deleteUser($data, $where){
        $result = $this->update($data, $where);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

}