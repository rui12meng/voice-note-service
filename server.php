<?php

/**
 * 服务器入口
 * $Id: server.php $
 */

// 定义应用路径
define('WEBPATH', __DIR__);
define('LSFPATH', WEBPATH . '/lsf');
define('APPPATH', WEBPATH . '/app');

// 加载框架入口文件
require_once LSFPATH . '/enter.php';

$manage = new Lsf\Manage();
$manage->run();