<?php
return [
    'name' => 'voice-note-service',
    'host' => '0.0.0.0',
    'port' => 9504,
    'pid_file_path' => WEBPATH . '/server.pid',
    'module' => [
        'redis' => TRUE,
        'mysql' => TRUE,
    ],
    'mysql' => [
        'node_name' => ['mysql']
    ],
];