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

    /**
     * 用户注册事务处理
     * @param  array  $data
     * @return mixed
     */
    public function userSign($data){
        $this->_svrDaoVnUserAuthModel = \Lsf\Loader::model('DaoVnUserAuth', false, APP_NAME_USER);

        try {
            // 1. 从连接池获取一个DB连接（手动管理）
            $db = \Lsf\Loader::plugin('MysqlPool')->db('mysql');
            // 2. 注入到两个Model
            $this->injectDb($db);
            $this->_svrDaoVnUserAuthModel->injectDb($db);
            // 3. 开启事务
            $userData = [
                'user_uid' => 'ujrri899wuww99',//uuid_create(UUID_TYPE_RANDOM),
                'username' => $data['username'] ?? '',
                'email' => $data['email'] ?? '',
                'register_type' => $data['provider'] ?? '',
                'is_guest' => 0,
            ];
            $this->begin();
            $userId = $this->storeData($userData);
            if ($userId < 0 ) {
                throw new \Exception('Insert user failed');
            }
            $authData = [
                'user_id' => $userId,
                'auth_type' => $data['provider'] ?? '',
                'identifier' => $data['identifier'] ?? '',
                'credential' => $data['credential'] ?? '',
                'last_login_at' => date('Y-m-d H:i:s'),
            ];
            $authId = $this->_svrDaoVnUserAuthModel->insert($authData);
            if ($authId === false) {
                throw new \Exception('Insert auth failed');
            }
            // 5. 提交事务
            $this->commit();

        }catch (\Exception $e){
            // 6. 回滚（如果已开启事务）
            if (isset($this)) {
                $this->rollback();
            }
            // 记录错误 & 抛出
            \Lsf\Loader::plugin('Log')->error(9000999, ['msg' => $e->getMessage()], 'user_register');
            throw $e;
        }finally {
            // 7. 手动归还DB连接
            if (isset($db)) {
                $db->recycle('mysql');
            }
        }

    }

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

    public function storeData($data){

        if(empty($data)|| !array($data)){
            return -1;
        }

        $result = $this->insert($data);
        if(isset($result) && !empty($result)){
            return $result;
        }else{//入库失败
            return -2;
        }

    }

}