<?php
namespace User\Model;

/**
 * 用户日志数据
 * @author mengrui
 * $Id: dao_vn_user_devices.php $
 */

class DaoVnUserDevices extends \Lsf\Model
{
    public $primary     = 'id';
    public $tablePrefix = '';
    public $table       = 'user_devices';

    /**
     * 构造函数
     * @param void
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function formatData($data=[]){
        $result = [];
        if(empty($data)){
            return $result;
        }
        foreach($data as $k => $v){
            if(isset($data[$k]) && !empty($data[$k])){
                $result[] = $k ."=". 'VALUE('.$k.')';
            }
        }
    }

    /**
     * 存储用户设备信息
     * @param  int     $uid 用户id
     * @param  string  $device_id 设备id
     * @param  array   $data
     * @return mixed
     */

    public function storeDevices($uid,$device_id,$data){

        $device_type = $data['device_type'] ?? '';
        $device_name = $data['device_name'] ?? '';
        $os_version = $data['os_version'] ?? '';
        $app_version = $data['app_version'] ?? '';
        $push_token = $data['push_token'] ?? '';
        $ip_address = $data['ip_address'] ?? '';

        // UPDATE 部分动态构造
        $updates = [];
        foreach($data as $k => $v){
            if(isset($data[$k]) && !empty($data[$k])){
                $updates[] = $k ."=". '"'.$v.'"';
            }
        }
        $updates[] = "last_login_at = CURRENT_TIMESTAMP";
        $updateSql = implode(", ", $updates);


        $sql = <<<SQL
        INSERT INTO {$this->table}
        (user_id, device_id, device_type, device_name, os_version, app_version, push_token, ip_address)
        VALUES
        ('$uid', '$device_id', '$device_type', '$device_name', '$os_version', '$app_version', '$push_token', '$ip_address')
        ON DUPLICATE KEY UPDATE
            $updateSql
SQL;
        $result = $this->query($sql);

        if($result === FALSE){
            return FALSE;
        }else{
            return $result;
        }
    }



}