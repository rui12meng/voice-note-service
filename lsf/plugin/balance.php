<?php
namespace Lsf\Plugin;

/**
 * 负载均衡插件类
 * @author mr
 * $Id: balance.php $
 */

class Balance
{
    const OFF_HOST_RETRY_INTERAL_TIME = 30; // 异常主机重试间隔时长，单位秒，默认为5
    const OFF_HOST_RETRY_TIMES = 3; // 重试多少次才算异常主机
    protected $swoole;
    private $_hosts = array(); // 服务列表
    private $_isHostError = FALSE; // 当前服务是否异常
    static private $_balanceHosts = array(); // 负载服务信息

    /**
     * 初始化配置
     * @param  string  $name
     * @param  array   $hostConfig
     * @param  string  $hostGroupName
     * @retrun void
     */
    public function initAllConfig($name, $hostConfig, $hostGroupName = ''){
        if(empty($name) || empty($hostConfig)){
            throw new \Exception('Balance Plugin Error: init_all_config config empty');
        }
        // 检查配置格式
        foreach($hostConfig as $key => $value){
            if(empty($value['host']) || empty($value['port'])){
                throw new \Exception('Balance Plugin Error: key ' . $key . ' host or port empty');
            }
        }
        // 多组配置都放在一个配置文件情况
        if($hostGroupName){
            if(isset($hostConfig[$hostGroupName])){
                $this->_hosts[$name] = $hostConfig[$hostGroupName];
            }else{
                throw new \Exception('Balance Plugin Error: ' . $hostGroupName . ' group not find');
            }
            // 各服务单配置文件独立
        }else{
            $this->_hosts[$name] = $hostConfig;
        }
    }

    /**
     * 主机选择
     * @param  string  $name
     * @return bool
     */
    private function _selectHost($name){
        // 判断是否有
        $key = $this->_getHostsKey($name);
        $hostNum = count($this->_hosts[$name]);
        if($hostNum == 0){
            $this->_errno = 101;
            $this->_errMsg = 'host pool empty';
            return FALSE;
        }
        if($hostNum == 1 || !isset(self::$_balanceHosts[$key]['currentIndex'])){
            self::$_balanceHosts[$key]['currentIndex'] = 0;
            return TRUE;
        }
        // 初始化当前主机数组下标（逐个选择）
        self::$_balanceHosts[$key]['currentIndex'] = (self::$_balanceHosts[$key]['currentIndex'] + 1) % $hostNum;
        // if(self::$_balanceHosts[$key]['currentIndex'] == 0){
        //     self::$_balanceHosts[$key]['currentIndex'] = $hostNum;
        // }
        if(!isset(self::$_balanceHosts[$key])){
            self::$_balanceHosts[$key] = [];
        }
        // 如果命中故障下线主机，重新选择主机
        $selectNum = 0;
        $hostMd5 = $this->_getCurrentHostMd5($name);
        while(isset(self::$_balanceHosts[$key]['offHost'][$hostMd5])){
            // 如果重试次数小于self::OFF_HOST_RETRY_TIMES阈值主机仍可用
            if(self::$_balanceHosts[$key]['offHost'][$hostMd5]['retryTimes'] < self::OFF_HOST_RETRY_TIMES){
                break;
            }else{
                // 当前时间距离最近一次重试时间大于self::OFF_HOST_RETRY_INTERAL_TIME阈值再给一次重试机会（自动恢复）
                if((time() - self::$_balanceHosts[$key]['offHost'][$hostMd5]['offTime']) > self::OFF_HOST_RETRY_INTERAL_TIME){
                    break;
                    // 逐个再选择一个主机
                }else{
                    self::$_balanceHosts[$key]['currentIndex'] = (self::$_balanceHosts[$key]['currentIndex'] + 1) % $hostNum;
                    // if(self::$_balanceHosts[$key]['currentIndex'] == 0){
                    //     self::$_balanceHosts[$key]['currentIndex'] = $hostNum;
                    // }
                }
                $hostMd5 = $this->_getCurrentHostMd5($name);
                $selectNum++;
                // 循环一圈没有可用机器，退出防止死循环
                if($selectNum > $hostNum){
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

    /**
     * 获取当前主机MD5串
     * @param  string  $name
     * @return string
     */
    private function _getCurrentHostMd5($name){
        $key = $this->_getHostsKey($name);
        return md5($this->_hosts[$name][self::$_balanceHosts[$key]['currentIndex']]['host'] . $this->_hosts[$name][self::$_balanceHosts[$key]['currentIndex']]['port']);
    }

    /**
     * 获取当前主机配置key
     * @param  string  $name
     * @return string
     */
    private function _getHostsKey($name){
        return md5(serialize($this->_hosts[$name]));
    }

    /**
     * 获取当前主机下标
     * @param  string  $name
     * @return mixed
     */
    public function getCurrentHostIndex($name){
        $result = $this->_selectHost($name);
        if($result){
            $key = $this->_getHostsKey($name);
            return self::$_balanceHosts[$key]['currentIndex'];
        }else{
            return FALSE;
        }
    }

    /**
     * 获取当前主机配置
     * @param  string  $name
     * @return mixed
     */
    public function getCurrentIndexHostConfig($name, $index){
        if(empty($this->_hosts[$name])){
            return FALSE;
        }
        // 非0的假
        if(!$index && $index != 0){
            return FALSE;
        }
        if(empty($this->_hosts[$name][$index])){
            throw new \Exception('Balance current index ' . $index . ' in _host not found');
        }else{
            return $this->_hosts[$name][$index];
        }
    }

    /**
     * 设置主机状态
     * @param  string  $name
     * @param  bool    $isError（主机是否异常）
     * @return void
     */
    public function setHostStatus($name, $isError){
        $hostMd5 = $this->_getCurrentHostMd5($name);
        $key = $this->_getHostsKey($name);
        // 主机异常
        if($isError){
            $this->_isHostError  = TRUE;
            // 如果是重试，重试次数加1，并更新异常时间
            if(isset(self::$_balanceHosts[$key]['offHost'][$hostMd5])){
                self::$_balanceHosts[$key]['offHost'][$hostMd5]['retryTimes']++;
                self::$_balanceHosts[$key]['offHost'][$hostMd5]['offTime'] = time();
                // 不是重试，需要把机器添加到offhost列表中，且记录异常时间
            }else{
                self::$_balanceHosts[$key]['offHost'][$hostMd5] = [];
                self::$_balanceHosts[$key]['offHost'][$hostMd5]['retryTimes'] = 0;
                self::$_balanceHosts[$key]['offHost'][$hostMd5]['offTime'] = time();
            }
            // 主机正常
        }else{
            // 如果是重试需要把机器从offhost列表中删除
            if(isset(self::$_balanceHosts[$key]['offHost'][$hostMd5])){
                unset(self::$_balanceHosts[$key]['offHost'][$hostMd5]) ;
            }
        }
    }

    /**
     * 获取主机配置
     * @param  void
     * @return array
     */
    public function getBalanceHosts(){
        return self::$_balanceHosts;
    }
}