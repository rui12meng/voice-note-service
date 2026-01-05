<?php
namespace Home\Controller;

/**
 * 默认控制器
 * @author mengrui
 * $Id: page.php $
 */
use Ramsey\Uuid\Uuid;

class Page extends \App\Application
{

    private $_userService;
    private $_uploadService;

    /**
     * @param $appName
     * @param $controllerName
     * @param $actionName
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);
    }
    /**
     * 默认action
     * @param  void
     * @return string
     */
    public function index(){
        $uuid4 = Uuid::uuid7()->toString();
        echo $uuid4; exit();// e.g. "550e8400-e29b-41d4-a716-446655440000"
        return $this->json(ECODE_SUCCESS);
    }
}