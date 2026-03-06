<?php
namespace H5\Controller;

/**
 * 默认控制器
 * @author mengrui
 * $Id: index.php $
 */

class Index extends \App\Application
{
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
     * 用户协议
     * @param  void
     * @return string
     */
    public function userPolicy(){
        $title = '用户协议';
        $content = 'test userPolicy';

        $this->renderHtml($content, $title);
    }

    /**
     * 隐私协议
     * @param  void
     * @return string
     */
    public function privacyPolicy(){
        $title = '隐私政策';
        $content = 'test privacyPolicy';

        $this->renderHtml($content, $title);
    }

    /**
     * 通用 H5 渲染方法
     * 核心逻辑：设置 Header + 输出完整 HTML 结构
     * @param string $content 协议正文内容
     * @param string $title 页面标题
     * @return void
     */
    private function renderHtml($content, $title)
    {

        $html = $content;

        // 3. 【关键】直接使用 Swoole 原生方法设置 Header
        // 这会覆盖框架默认的 Content-Type: application/json
        $this->setheader('Content-Type', 'text/html; charset=utf-8');

        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

        // 4. 直接发送内容
        echo ($html);
        exit();
    }
}