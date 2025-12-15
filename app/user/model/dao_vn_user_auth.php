<?php
namespace User\Model;

/**
 * 用户数据
 * @author mengrui
 * $Id: dao_user.php $
 */

class DaoVnUserAuth extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'user_auth';

    /**
     * 根据授权服务商及授权uid获取用户信息
     * @param  array   $data
     * @return mixed
     */

    public function findOauthInfo($data){
        $where = [
            'auth_type' => $data['auth_type'],
            'identifier' => $data['identifier'],
        ];
        $columns = 'id';
        $result = $this->query($columns ,  $where);

        return $result;

    }

    /**
     * 查询
     * @param  string  $columns
     * @param  array   $where
     * @param  int     $limit
     * @return mixed
     */
    public function query($columns = '*',  $where = [], $limit = 1){

        $result = $this->select($columns, $where, $this->primary . ' DESC', $limit);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

    /**
     * 更新oauth信息
     * @param  array   $data
     * @param  array   $where
     * @return mixed
     */
    public function updateOauth($data, $where){
        $result = $this->update($data, $where);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }

}