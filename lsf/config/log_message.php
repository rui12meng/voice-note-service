<?php

/**
 * 框架日志错误信息
 * @author mr
 * $Id: log_message.php $
 */

return [
    9000001 => '主进程pid写入文件失败',
    9000002 => '服务启动时自动注册任务worker失败',
    9000003 => 'onRequest回调函数捕获异常',
    9000004 => 'onTask回调函数data参数数据异常',
    9000005 => 'onTask回调函数捕获异常',
    9000006 => 'onTask回调函数callback数据json格式转换错误',
    9000007 => 'onFinish回调函数data参数数据异常',
    9000008 => 'onFinish回调函数不需要使用finish异常返回结果',
    9000009 => 'onFinish回调函数捕获异常',
    9000010 => 'php_shutdown错误',
    9000011 => 'php自定义错误',
    9000012 => 'api-gzip已开启但gzdecode失败',
    9000013 => 'header中content-type为json但json_decode失败',
    9000014 => 'mysql连接异常',
    9000015 => 'mysql连接重置后重试仍然异常',
    9000016 => 'mysql连接池分配空闲连接异常',
    9000017 => 'redis连接池分配空闲连接异常',
    9000018 => 'RedisException异常',
    9000019 => 'redis连接重置后重试仍然异常',
    9000020 => 'mysql查询时未指定节点名称',
    9000021 => 'mysql执行失败',
];