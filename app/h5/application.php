<?php
namespace App\H5;

/**
 * 框架静态链接基类（html）
 * @author mengrui
 * $Id: application.php $
 */

define('APP_NAME_H5', 'h5');

class Application
{
    private $_controller;

    /**
     * 构造函数
     * @param  object  $controller
     * @return void
     */
    public function __construct($controller){
        $this->_controller = $controller;
    }

    /**
     * 初始化
     * @param  void
     * @return void
     */
    public function _init(){

    }
}