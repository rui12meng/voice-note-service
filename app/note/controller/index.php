<?php
namespace Note\Controller;


class Index extends \App\Application
{

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);

    }

    /**
     * 笔记分析
     * @param  void
     * @return void
     */
    public function analysis(){
        echo 'why';exit();
    }
}
