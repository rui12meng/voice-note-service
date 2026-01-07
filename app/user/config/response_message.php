<?php

/**
 * 用户模块接口响应错误信息
 * @author mr
 * $Id: response_message.php $
 */

/**
 * 错误码定义规则
 * 取值范围：[1002000,1002499]
 * 前三位：100（voice-note-service项目标识）
 * 第四位：2（用户模块标识）
 * 后三位：[0,499]（可定义500个不重复的错误码）
 */

return [
    1002001 => 'id_token无效',
    1002002 => '参数异常', //guest 登录缺少deviceId
    1002003 => '获取token失败', //登录态生成失败
    1002004 => 'id_token无效',
    1002005 => '用户已注销',
    1002006 => 'refresh token 无效',
    1002007 => 'redis操作失败',
    1002008 => 'access token 获取失败',
    1002009 => '用户已登出',
    1002010 => 'Only JPG/PNG/GIF allowed',
    1002011 => 'Invalid or empty file',
    1002012 => 'File too large (max 2MB)',
    1002013 => 'File upload oss error',
    1002014 => 'File upload general error',
    1002015 => '文件上传失败',
];
