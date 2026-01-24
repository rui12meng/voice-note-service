<?php

function ocrLoader($class)
{
    $path = str_replace('AlibabaCloud\SDK\Ocrapi\V2021070\\', '', $class);
    $file = __DIR__ . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . str_replace('\\', \DIRECTORY_SEPARATOR, $path) . '.php';
    if (file_exists($file)) {
        require_once $file;

        return true;
    }

    return false;
}

spl_autoload_register('ocrLoader');
