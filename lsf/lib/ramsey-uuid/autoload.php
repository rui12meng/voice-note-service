<?php

function uuidLoader($class)
{
    $prefix = 'Ramsey\\Uuid\\';

    // 只处理 ramsey/uuid 的类
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));

    $file = __DIR__
        . DIRECTORY_SEPARATOR . 'src'
        . DIRECTORY_SEPARATOR . 'Ramsey'
        . DIRECTORY_SEPARATOR . 'Uuid'
        . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
        . '.php';

    if (is_file($file)) {
        require $file;
        return true;
    }
}
spl_autoload_register('uuidLoader');