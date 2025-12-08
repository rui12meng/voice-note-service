<?php
namespace Lsf;
/**
 * Env配置文件获取
 * @author mr
 * $Id: function.php $
 */
class Env
{
    private static $data = [];

    public static function load($file)
    {
        if (!file_exists($file)) return;

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // 跳过注释
            if (strpos(trim($line), '#') === 0) continue;

            // key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);

                $key = trim($key);
                $value = trim($value);

                // 去掉值两端的引号
                $value = trim($value, "\"'");

                self::$data[$key] = $value;

                // 注册到 $_ENV
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get($key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }

    // 根据前缀获取分组
    public static function group($prefix)
    {
        $group = [];
        foreach (self::$data as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $name = strtolower(substr($key, strlen($prefix)));
                $group[$name] = $value;
            }
        }
        return $group;
    }
}