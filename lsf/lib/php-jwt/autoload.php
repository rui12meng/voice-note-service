<?php
function jwtLoader($class)
{
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . '/' . $path . '.php';
    if (file_exists($file)) {
        include_once($file);
    }
}
spl_autoload_register('jwtLoader');
