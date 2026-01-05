<?php
function uuidLoader($class)
{
    if (strpos($class, 'Ramsey\\Uuid') === 0) {
        // 去掉命名空间前缀 "Ramsey\\Uuid"
        // 注意：这里保留后面的子命名空间（如 \Codec\StringCodec）
        $relativePath = substr($class, strlen('Ramsey\\Uuid'));

        // 如果是根类（如 Ramsey\Uuid\Uuid），$relativePath 为空或以 \\ 开头
        if ($relativePath === '') {
            $filePath = 'Uuid.php';
        } else {
            // 去掉开头的反斜杠，并转为目录分隔符
            $relativePath = ltrim($relativePath, '\\');
            $filePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativePath) . '.php';
        }

        $file = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $filePath;

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
spl_autoload_register('uuidLoader');
