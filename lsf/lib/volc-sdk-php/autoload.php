<?php
function volcOcrLoader($class)
{
//    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
//    $file = __DIR__ . '/' . $path . '.php';
//    if (file_exists($file)) {
//        include_once($file);
//    }

    // 只处理以 "Volc\" 开头的类（避免影响其他库）
    if (strpos($class, 'Volc\\') !== 0) {
        return;
    }

    // 将命名空间转为路径，并加上 src/ 前缀
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . '/src/' . $relativePath . '.php';

    if (file_exists($file)) {
        include_once $file;
    }
}
spl_autoload_register('volcOcrLoader');
