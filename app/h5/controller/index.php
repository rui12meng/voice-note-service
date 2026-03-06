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
        header('Content-Type: text/html; charset=utf-8');

        // 禁止缓存 , 确保协议更新后立即生效
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // 转义内容以防 XSS
        $safeContent = nl2br(htmlspecialchars($content));
        $safeTitle = htmlspecialchars($title);

        // 【关键步骤 3】输出完整的 H5 结构
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <!-- 【核心】视口设置：适配移动端，禁止用户缩放，模拟原生 App 体验 -->
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
            <title><?php echo $safeTitle; ?></title>

            <style>
                /* 全局重置 */
                * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background-color: #f7f8fa;
                    color: #333;
                    line-height: 1.8;
                    font-size: 16px;
                    /* 适配 iPhone 底部安全区 */
                    padding-bottom: env(safe-area-inset-bottom, 20px);
                }

                /* 容器：限制最大宽度，保证在 iPad 或横屏手机上阅读体验良好 */
                .container {
                    max-width: 750px;
                    margin: 0 auto;
                    background: #fff;
                    min-height: 100vh;
                    padding: 24px 20px;
                    box-shadow: 0 0 20px rgba(0,0,0,0.03);
                }

                /* 标题样式 */
                h1 {
                    font-size: 20px;
                    font-weight: 600;
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #eee;
                    color: #1a1a1a;
                }

                /* 正文样式 */
                .content {
                    font-size: 15px;
                    color: #444;
                    text-align: justify; /* 两端对齐更美观 */
                }

                .content p {
                    margin-bottom: 15px;
                }

                .content strong {
                    color: #000;
                    font-weight: 600;
                }

                /* 底部提示 */
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    font-size: 12px;
                    color: #999;
                    padding-bottom: 20px;
                }
            </style>
        </head>
        <body>

        <div class="container">
            <h1><?php echo $safeTitle; ?></h1>

            <div class="content">
                <?php echo $safeContent; ?>
            </div>

        </div>

        </body>
        </html>
        <?php
        // 如果框架需要 return 响应对象而不是直接 echo，请在此处构建 Response 对象返回
        // 例如：return response()->content($htmlString);
        // 对于大多数传统 MVC，直接 echo 后执行 exit; 即可防止框架追加布局
        exit;
    }
}