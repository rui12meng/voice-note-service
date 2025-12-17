<?php
namespace App\Note;

/**
 * 语音笔记基类
 * @author mengrui
 * $Id: application.php $
 */

define('APP_NAME_NOTE', 'note');

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