<?php

/**
 * 框架入口
 * $Id: enter.php $
 */

require_once LSFPATH . '/function.php';
require_once LSFPATH . '/env.php';

// 定义常量
define('VERSION', '1.0.0');
define('LSF_ERROR_TAG', 'lsf_go_wrong');
// 定义时区
date_default_timezone_set('Asia/Shanghai');
// 加载Loader
require_once 'loader.php';
// 注册命名空间
Lsf\Loader::addNameSpace('App', APPPATH); // 应用
Lsf\Loader::addNameSpace('Lsf', LSFPATH); // 框架
Lsf\Loader::addNameSpace('Model', WEBPATH . '/model'); // model
Lsf\Loader::addNameSpace('Service', WEBPATH . '/service'); // service
Lsf\Loader::addNameSpace('Lib', LSFPATH . '/lib'); // 自有库
Lsf\Loader::addNameSpace('Smf', LSFPATH . '/lib'); // 连接池库
Lsf\Loader::addNameSpace('Database', LSFPATH . '/lib/database'); // 数据库
// 自动加载
spl_autoload_register('\\Lsf\\Loader::autoload');