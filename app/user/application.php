<?php
namespace App\User;

/**
 * 用户模块基类
 * @author mr
 * $Id: application.php $
 */

define('APP_NAME_USER', 'user');

class Application
{
    private $_controller;

    /**
     * 构造函数
     * @param object $controller
     * @return void
     */
    public function __construct($controller){
        $this->_controller = $controller;
    }

    /**
     * 应用初始化
     * @param void
     * @return void
     */
    public function _init(){

    }

}
