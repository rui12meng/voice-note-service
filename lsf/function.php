<?php
/**
 * 函数文件
 * @author
 * $Id: function.php $
 */

/**
 * 字符串下划线转换驼峰
 * user_center => UserCenter
 * @param  string  $camelCaps  待转换字符串
 * @param  int     $mode       转换模式（0-小驼峰 1-大驼峰）
 * @param  string  $separator  分隔符
 * @return string
 */
function camelize($uncamelizedWords, $mode = 1, $separator = '_'){
    $uncamelizedWords = str_replace($separator, ' ', strtolower($uncamelizedWords));
    return $mode == 1 ? str_replace(' ', '', ucwords($uncamelizedWords)) : str_replace(' ', '', lcfirst(ucwords($uncamelizedWords)));
}

/**
 * 驼峰转换字符串下划线
 * userCenter => user_center / UserCenter => user_center
 * @param  string  $camelCaps  待转换字符串
 * @param  string  $separator  分隔符
 * @return string
 */
function uncamelize($camelCaps, $separator = '_'){
    return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
}

/**
 * 加载文件配置
 * @param  string  $filePath
 * @param  int     $fileForamt（1-php 2-ini）
 * @return mixed
 */
function load_file_config($filePath, $fileFormat){
    if(is_file($filePath)){
        $config = [];
        switch($fileFormat){
            // .php
            case 1:
                $config = include($filePath);
                break;
            // .ini
            case 2:
                $config = parse_ini_file($filePath, TRUE);
                break;
        }
        return $config;
    }else{
        return FALSE;
    }
}

/**
 * 获取唯一id
 * @param  void
 * @return void
 */
function getUqId(){
    return uniqid(time() . mt_rand(100000, 999999));
}

/**
 * 通用映射查询函数
 * @param mixed $input 查询条件 (可以是单个值/键，也可以是数组)
 * @param string $direction 查询方向: 'key_to_value' (默认) 或 'value_to_key'
 * @return mixed 返回对应的值/键，如果输入是数组则返回关联数组；找不到返回 null 或跳过
 */
function emotionMap($input, $direction = 'key_to_value') {
    $emotionMap = [
        1 => 'joy' , //'快乐'
        2 =>'sadness' , //'悲伤'
        3 => 'anger' , //'愤怒'
        4 => 'fear' , //'焦虑'
        5 => 'surprise' , //'惊讶'
        6 => 'disgust' , // '厌恶'
        7 => 'neutral' , //'平静'
    ];
    // 构建反向映射缓存 (Value => Key)，仅当需要反向查询时构建
    static $reverseMapCache = null;

    if ($direction === 'value_to_key' && $reverseMapCache === null) {
        $reverseMapCache = array_flip($emotionMap);
    }

    $targetMap = ($direction === 'key_to_value') ? $emotionMap : $reverseMapCache;

    // 情况 A: 输入是数组 (批量查询)
    if (is_array($input)) {
        $result = [];
        foreach ($input as $item) {
            // 递归调用自己处理单个元素，保持逻辑统一
            $res = emotionMap($emotionMap, $item, $direction);
            if ($res !== null) {
                $result[] = $res;
            }
        }
        return $result;
    }

    // 情况 B: 输入是单个值 (标量查询)
    // 严格检查键是否存在
    $inputValue = strtolower(trim((string)$input));
    if (array_key_exists($inputValue, $targetMap)) {
        return $targetMap[$input];
    }

    return null;
}

/**
 * 字符串加解密
 * @param  string  $string
 * @param  string  $operation
 * @param  string  $key
 * @param  int     $expiry
 * @return mixed
 */
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0){
    $strConfig = array('+' => '@', '/' => '.');
    // 需要先转换
    if($operation == 'DECODE'){
        foreach($strConfig as $k => $v){
            $string = str_replace($v, $k, $string);
        }
    }
    // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
    $ckey_length = 4;
    // 密匙
    $key = md5($key ? $key : md5('lsf_swoole_framework'));
    // 密匙a会参与加解密
    $keya = md5(substr($key, 0, 16));
    // 密匙b会用来做数据完整性验证
    $keyb = md5(substr($key, 16, 16));
    // 密匙c用于变化生成的密文
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length):
        substr(md5(microtime()), -$ckey_length)) : '';
    // 参与运算的密匙
    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);
    // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，
    //解密时会通过这个密匙验证数据完整性
    // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) :
        sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);
    $result = '';
    $box = range(0, 255);
    $rndkey = array();
    // 产生密匙簿
    for($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }
    // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
    for($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }
    // 核心加解密部分
    for($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        // 从密匙簿得出密匙进行异或，再转成字符
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }
    if($operation == 'DECODE') {
        // 验证数据有效性，请看未加密明文的格式
        if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) &&
            substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        $return = $keyc.str_replace('=', '', base64_encode($result));
        // 定义特殊字符转义
        foreach($strConfig as $k => $v){
            $return = str_replace($k, $v, $return);
        }
        // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
        // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
        return $return;
    }
}