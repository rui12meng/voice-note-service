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
        $this->setheader('Content-Type', 'text/html; charset=utf-8');

        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

        // 构建 HTML 字符串
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <!-- 关键：设置视口，禁止缩放，适配所有手机屏幕 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no, email=no">
    <title>用户服务协议</title>
    <style>
        /* --- 全局重置与基础样式 --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent; /* 移除点击高亮 */
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f7f8fa; /* 浅灰背景，护眼 */
            font-size: 16px;
            /* 适配全面屏底部安全区 (iPhone X+) */
            padding-bottom: env(safe-area-inset-bottom); 
        }

        /* --- 容器样式 --- */
        .container {
            max-width: 800px; /* 平板/横屏限制最大宽度 */
            margin: 0 auto;
            background-color: #ffffff;
            padding: 24px 20px;
            min-height: 100vh;
        }

        /* --- 标题样式 --- */
        h1 {
            font-size: 22px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 24px;
            color: #1a1a1a;
            line-height: 1.4;
        }

        h2 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 24px;
            margin-bottom: 12px;
            color: #222;
        }

        /* --- 段落样式 --- */
        p {
            margin-bottom: 16px;
            text-align: justify; /* 两端对齐，更美观 */
            font-size: 15px; /* 正文稍小，适合阅读 */
            color: #444;
        }

        /* --- 列表样式 --- */
        ul, ol {
            margin-bottom: 16px;
            padding-left: 20px;
        }
        
        li {
            margin-bottom: 8px;
            font-size: 15px;
            color: #444;
        }

        /* --- 强调文字 --- */
        strong {
            color: #000;
            font-weight: 600;
        }

        /* --- 底部更新信息 --- */
        .update-time {
            margin-top: 40px;
            text-align: right;
            font-size: 13px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 16px;
        }

        /* --- 禁用长按选中 (App 内体验更好) --- */
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        /* 允许用户选中协议中的特定段落 (如果需要) */
        .selectable {
            -webkit-user-select: text;
            user-select: text;
        }
    </style>
</head>
<body>

<div class="container selectable">
    <h1>用户服务协议</h1>

    <p><strong>欢迎您使用本服务！</strong></p>
    
    <p>在您使用本应用提供的服务之前，请您务必仔细阅读并充分理解本协议所有内容。一旦您开始使用本服务，即视为您已阅读并同意受本协议约束。</p>

    <h2>一、服务内容</h2>
    <p>本应用致力于为您提供便捷的信息查询与交互服务。具体服务内容包括但不限于：资讯浏览、功能操作、账户管理等。我们有权根据业务发展需要，随时变更、中断或终止部分或全部服务，而无需事先通知您。</p>

    <h2>二、用户行为规范</h2>
    <p>您在使用本服务时，必须遵守中华人民共和国相关法律法规。您承诺不得利用本服务制作、复制、发布、传播含有下列内容的信息：</p>
    <ol>
        <li>反对宪法所确定的基本原则的；</li>
        <li>危害国家安全，泄露国家秘密，颠覆国家政权，破坏国家统一的；</li>
        <li>散布谣言，扰乱社会秩序，破坏社会稳定的；</li>
        <li>散布淫秽、色情、赌博、暴力、凶杀、恐怖或者教唆犯罪的；</li>
        <li>侮辱或者诽谤他人，侵害他人合法权益的；</li>
        <li>含有法律、行政法规禁止的其他内容的。</li>
    </ol>

    <h2>三、隐私保护</h2>
    <p>我们非常重视您的隐私保护。我们将严格按照《隐私政策》收集、使用、存储和分享您的个人信息。除非法律法规另有规定或征得您的同意，我们不会向任何第三方提供您的个人隐私信息。</p>

    <h2>四、知识产权声明</h2>
    <p>本应用包含的所有内容（包括但不限于文字、图片、音频、视频、图表、界面设计、代码等）均受版权法、商标法及其他知识产权法律法规的保护，归本应用运营方所有。未经书面许可，任何人不得擅自使用、复制、转载或镜像。</p>

    <h2>五、免责声明</h2>
    <p>1. 对于因不可抗力（如黑客攻击、电信部门技术调整、病毒侵袭等）导致的服务中断或数据丢失，我们不承担责任，但将尽力减少因此给您造成的损失和影响。<br>
    2. 您通过本服务获取的任何建议或信息，无论口头或书面，均不构成对我们的任何形式的保证。</p>

    <h2>六、协议的修改与终止</h2>
    <p>我们有权根据需要不时地修改本协议条款。一旦条款发生变动，我们将会将修改后的条款公布在应用内。如果您不同意修改后的条款，请立即停止使用本服务；如果您继续使用，则视为接受修改后的协议。</p>

    <h2>七、法律适用与管辖</h2>
    <p>本协议之效力、解释、变更、执行与争议解决均适用中华人民共和国法律。如双方就本协议内容或其执行发生任何争议，应尽量友好协商解决；协商不成时，任何一方均可向运营方所在地人民法院提起诉讼。</p>

</div>

</body>
</html>
HTML;

        //【必须】返回字符串，满足框架 is_string($result) 的检查
        return $html;
    }

    /**
     * 隐私协议
     * H5 渲染
     * @param  void
     * @return string
     */
    public function privacyPolicy(){
        $this->setheader('Content-Type', 'text/html; charset=utf-8');

        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

        // 构建 HTML 字符串
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>隐私政策</title>
    <style>body{font-family:sans-serif;padding:20px;}</style>
</head>
<body>
    <h1>隐私政策</h1>
    <p>这里是隐私政策的具体内容...</p>
</body>
</html>
HTML;

        //【必须】返回字符串，满足框架 is_string($result) 的检查
        return $html;
    }
}