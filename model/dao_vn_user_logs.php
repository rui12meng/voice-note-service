<?php
namespace Model;

/**
 * 用户日志数据
 * @author mengrui
 * $Id: dao_vn_user_logs.php $
 */

class DaoVnUserLogs extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'user_logs';

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
     * 存储日志信息
     * @param  array   $data
     * @return mixed
     */

    public function storeLogs($data){
        $result = $this->insert($data);
        if($result === FALSE){
            return FALSE;
        }else{
            return $result; //主键id
        }
    }



}