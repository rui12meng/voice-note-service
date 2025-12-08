<?php
namespace Lsf;

/**
 * 服务器进程管理类
 * @author mr
 * $Id: manage.php $
 */

class Manage
{
    const WAIT_ALL_WORKER_PROCESS_CLOSED_TIME = 5; // 单位秒

    private $_command = '';
    private $_allowCommandArr = ['start', 'stop', 'restart'];
    private $_allowEnvArr = ['local', 'qa', 'prod'];
    private $_environment = '';
    private $_serverConfig = [];
    private $_displayServerName = '';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        // 命令行参数验证
        $this->_cliParamValidate();
        // 加载本地服务器配置
        $this->_loadServerConfig();
    }

    /**
     * 命令行参数验证
     * [说明]
     * php server.php {start|stop|reload} {local|dev|qa|prod}
     * @param  void
     * @return void
     */
    private function _cliParamValidate(){
        $allowCommandStr = '{' . implode('|' , $this->_allowCommandArr) . '}';
        $allowEnvStr = '{' . implode('|' , $this->_allowEnvArr) . '}';
        global $argv;
        $fileName = trim($argv[0]);
        // 未获取到命令参数
        if(!isset($argv[1])){
            exit('Usage: php ' . $fileName . ' ' . $allowCommandStr . "\n");
        }else{
            $command = trim($argv[1]);
        }
        // 未获取到环境变量默认为生产环境
        if(!isset($argv[2])){
            $environment = 'prod';
        }else{
            $environment = trim($argv[2]);
            $this->_environment = $environment;
        }
        // 命令参数非法
        if(!in_array($command, $this->_allowCommandArr)){
            exit('Illegal command, Please use ' . $allowCommandStr . "\n");
        }else{
            $this->_command = $command;
        }
        // 环境变量非法
        if(!in_array($environment, $this->_allowEnvArr)){
            exit('Illegal environment, Please use ' . $allowEnvStr . "\n");
        }
        // 加载框架核心类设置环境变量
        \Lsf\Core::getInstance()->setEnvironment($environment);
    }

    /**
     * 加载服务器配置
     * @param  void
     * @return void
     */
    private function _loadServerConfig(){
        // 加载本地服务器配置
        $this->_serverConfig = Loader::config('server', TRUE);
        if(empty($this->_serverConfig)){
            exit('System server config empty');
        }
        if(!isset($this->_serverConfig['name']) || empty($this->_serverConfig['name'])){
            exit('System server config missing name field or value');
        }else{
            $this->_displayServerName = '[' . $this->_serverConfig['name'] . ']';
        }
        if(!isset($this->_serverConfig['host']) || empty($this->_serverConfig['host'])){
            exit('System server config missing host field or value');
        }
        if(!isset($this->_serverConfig['port']) || empty($this->_serverConfig['port'])){
            exit('System server config missing port field or value');
        }
    }

    /**
     * 获取当前主进程的pid
     * @param  void
     * @return void
     */
    private function _getCurrentPid(){
        if (!isset($this->_serverConfig['pid_file_path']) || empty($this->_serverConfig['pid_file_path'])){
            exit('System server config missing pid_file_path field or value');
        }
        $filePid = 0;
        if (file_exists($this->_serverConfig['pid_file_path'])){
            $filePid = file_get_contents($this->_serverConfig['pid_file_path']);
        }
        // 定义命令
        $execCommand = 'ps -ef | grep php | grep ' . $this->_serverConfig['name'] . ' | grep -v grep | grep -v restart';
        exec($execCommand, $resultA);
        $commandPid = 0;
        if (count($resultA) > 1){
            $parseResult = explode(' ', $resultA[0]);
            $commandPid = $parseResult[3];
        }
        // 文件pid存在但实际命令查询不到进程
        if ($filePid && !$commandPid){
            // 检查文件中的pid是否真的存在
            $execCommand = 'ps ax | awk \'{ print $1 }\' | grep -e "^' . $filePid . '$"';
            exec($execCommand, $resultB);
            // 进程存在
            if (count($resultB) > 0){
                return $filePid;
            }else{
                // 由于ctrl+c无法触发事件所以需要手动清除
                file_put_contents($this->_serverConfig['pid_file_path'], '');
                return 0;
            }
            // 文件pid和命令行ps查询不一致
        }elseif (!$filePid && $commandPid){
            exit('System parse pid exception, please check pid file or use ps command');
            // 服务已停止
        }elseif(!$filePid && !$commandPid){
            return 0;
        }else{
            if ($filePid == $commandPid){
                return $filePid;
            }else{
                exit('System file and command parse pid not same, please check');
            }
        }
    }

    /**
     * 启动
     * @param  void
     * @return void
     */
    private function _start(){
        $pid = $this->_getCurrentPid();
        if ($pid){
            exit($this->_serverConfig['name'] . ' is running, not need start');
        }else{
            echo $this->_displayServerName . ' is loading config ......' . "\n";
            Env::load(WEBPATH . '/.env');
            $remoteConfig['crontab'] = Env::group('CRONTAB_') ?? [];
            $remoteConfig['server'] = Env::group('SERVER_') ?? [];
            $remoteConfig['swoole'] = Env::group('SWOOLE_') ?? [];
            
            if(!isset($remoteConfig['server']) || empty($remoteConfig['server'])){
                exit('System remote zookeeper config unchange.server empty');
            }elseif(isset($this->_serverConfig['remote'])){
                exit('System server config file not allow use `remote` keyword');
            }else{
                $this->_serverConfig['remote'] = $remoteConfig['server'];
            }
            // swoole配置
            if(!isset($remoteConfig['swoole']) || empty($remoteConfig['swoole'])){
                exit('System remote config swoole empty');
            }else{
                $swooleConfig = $remoteConfig['swoole'];
            }
            // crontab配置
            $crontabConfig = [];
            if(isset($remoteConfig['crontab'])){
                $crontabConfig = $remoteConfig['crontab'];
            }

            echo $this->_displayServerName . ' is starting ......' . "\n";
            $server = new Server\WebServer(
                $this->_serverConfig['host'],
                $this->_serverConfig['port'],
                $this->_serverConfig,
                $swooleConfig,
                $crontabConfig
            );
            $server->run();
        }
    }

    /**
     * 停止
     * @param  void
     * @return void
     */
    private function _stop(){
        $pid = $this->_getCurrentPid();
        if (!$pid){
            exit($this->_serverConfig['name'] . ' not running, not need stop');
        }else{
            echo $this->_displayServerName . ' is stopping ......' . "\n";
            exec('kill -15 ' . $pid, $result);
            echo $this->_displayServerName . ' is waitting for all worker process closed' . "\n";
            sleep(self::WAIT_ALL_WORKER_PROCESS_CLOSED_TIME);
            // 检查是否完成关闭
            $cmd = 'ps ax | awk \'{ print $1 }\' | grep -e "^' . $pid . '$"';
            exec($cmd, $result);
            // 进程存在搂底逻辑
            if (count($result) > 0){
                $cmd = 'ps -ef | grep ' . $this->_serverConfig['name'] . ' | grep -v grep | grep -v stop | grep -v restart | awk \'{print $2}\' | uniq';
                exec($cmd, $ids);
                if(!empty($ids)){
                    echo $this->_displayServerName . ' is still running, start force close' . "\n";
                    $idsCmd = '';
                    foreach($ids as $id){
                        $idsCmd.= ' ' . $id;
                    }
                    $cmd = 'kill -9 ' . $idsCmd;
                    exec($cmd, $result);
                }
            }
            echo $this->_displayServerName . ' is stoped' . "\n";
        }
    }

    /**
     * 重启
     * @param  void
     * @return void
     */
    private function _restart(){
        $this->_stop();
        $cmd = 'php ' . dirname(__DIR__) . '/server.php start';
        if($this->_environment){
            $cmd.= ' ' . $this->_environment;
        }
        exec($cmd, $result);
        if(!empty($result)){
            foreach($result as $outputStr){
                echo $outputStr . "\n";
            }
        }
    }

    /**
     * 热重载
     * @param  void
     * @return void
     */
    // private function _reload(){
    //     $pid = $this->_getCurrentPid();
    //     if (!$pid){
    //         exit($this->_serverConfig['name'] . ' not running, can not reload');
    //     }else{
    //         posix_kill($pid, SIGUSR1);
    //         echo $this->_displayServerName . ' reload success' . "\n";
    //     }
    // }

    /**
     * 执行
     * @param  void
     * @return void
     */
    public function run(){
        switch ($this->_command){
            // 启动
            case 'start':
                $this->_start();
                break;
            // 停止
            case 'stop':
                $this->_stop();
                break;
            // 重启
            case 'restart':
                $this->_restart();
                break;
            // 热重载
            // case 'reload':
            //     $this->_reload();
            // break;
        }
    }
}
