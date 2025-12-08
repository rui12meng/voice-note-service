<?php

/**
 * 公共接口响应错误信息
 * $Id: response_message.php $
 * @author mr
 */

/**
 * 错误码定义规则
 * 取值范围：[1000000,1000499]
 * 前三位：100（voice-note-service项目标识）
 * 第四位：0（公共模块标识）
 * 后三位：[0,499]（可定义500个不重复的错误码）
 */

define('GLOBAL_NETWORK_EXCEPTION', ['网络不给力']);
define('APPLY_CLASS_SUCC', ['申请成功']);
define('USER_LOGIN_TOKEN_EXPIRE', ['帐号信息过期,请重新登录']);
define('USER_LOGIN_OTHER_DEVICE', ['你的帐号已在其他设备登录']);
define('CODE_SEND_FREQUENTLY', ['发送验证码过于频繁']);

return [
    0       => [
        'message' => 'success',
        'desc' => [
            'student_join_class_class_code_apply' => APPLY_CLASS_SUCC,
        ],
    ],
    1000000 => '缺少参数',
    1000001 => '参数值非法',
    1000002 => '数据库查询失败',
    1000003 => [
        'message' => 'token过期',
        'desc' => ['default' => USER_LOGIN_TOKEN_EXPIRE],
    ],
    1000004 => '数据不存在',
    1000005 => [
        'message' => '接口网络请求失败',
        'desc' => ['default' => GLOBAL_NETWORK_EXCEPTION],
    ],
    1000006 => [
        'message' => '接口响应数据异常',
        'desc' => ['default' => GLOBAL_NETWORK_EXCEPTION],
    ],
    1000007 => '调用内部方法参数错误',
    1000008 => [
        'message' => '发送短信过于频繁',
        'desc' => ['default' => CODE_SEND_FREQUENTLY],
    ],
    1000009 => '发送短信失败',
    1000010 => '未知错误',
    1000011 => '接口响应错误',
    1000012 => [
        'message' => '已在其他设备登录',
        'desc' => ['default' => USER_LOGIN_OTHER_DEVICE],
    ],
    1000016 => '提交失败，内容涉嫌违规',
    1000100 => '请输入正确的小组码或扫描有效二维码',
    1000207 => '未能识别到学科',
    1000208 => '未能识别到错误标识',
    9999998 => [
        'message' => '接口暂时关闭',
        'desc' => ['default' => ['功能暂不可用，请您稍后再试']],
    ],
];
