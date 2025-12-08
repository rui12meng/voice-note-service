<?php
namespace Lsf\Plugin;

/**
 * 异步任务插件类
 * @author mr
 * $Id: Task.php $
 */

class Task
{
    private $_taskParams = [];
    private $_callbackParams;

    /**
     * 非阻塞任务投递
     * [说明]
     * ['task_uri' => '/app/controller/action', 'data' => ['a' => 1]];
     * @param  array  $data
     * @return void
     */
    public function task($data){
        if(empty($data) || !is_array($data)){
            return FALSE;
        }
        $server = \Lsf\Core::$php->getServer();
        $requestId = \Lsf\Core::$php->getRequestId();
        $data['data']['request_id'] = $requestId ? $requestId : getUqId();
        return $server->task($data);
    }

    /**
     * 阻塞任务投递
     * @param  array  $data
     * @return void
     */
    public function taskWait($data){
        if(empty($data) || !is_array($data)){
            return FALSE;
        }
        $server = \Lsf\Core::$php->getServer();
        $requestId = \Lsf\Core::$php->getRequestId();
        $data['data']['request_id'] = $requestId ? $requestId : getUqId();
        return $server->taskwait($data);
    }

    /**
     * 配置任务参数
     * @param  array  $data
     * @return void
     */
    public function setTaskParams($data){
        if(empty($data) || !is_array($data)){
            return FALSE;
        }else{
            if(isset($data['request_id']) && !empty($data['request_id'])){
                \Lsf\Core::$php->setRequestCommonParams('request_id', $data['request_id']);
            }
            $this->_taskParams = $data;
        }
    }

    /**
     * 获取任务参数
     * @param  string  $name
     * @return void
     */
    public function getTaskParam($name){
        return isset($this->_taskParams[$name]) ? $this->_taskParams[$name] : FALSE;
    }

    /**
     * 配置回调参数
     * @param  mixed  $data
     * @return void
     */
    public function setCallbackParams($data){
        $this->_callbackParams = $data;
    }

    /**
     * 获取回调参数
     * @param  void
     * @return void
     */
    public function getCallbackParam(){
        return $this->_callbackParams;
    }
}