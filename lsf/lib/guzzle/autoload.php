<?php

function guzzleLoader($class)
{
    $prefix = 'GuzzleHttp\\';
    $prefixLen = strlen($prefix);

    if (strncmp($class, $prefix, $prefixLen) !== 0) {
        return;
    }

    $relative = substr($class, $prefixLen);
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
}

spl_autoload_register('guzzleLoader');
