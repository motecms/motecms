<?php
declare(strict_types=1);

return [
    'site_name' => 'MoteCMS',
    'default_page' => 'home',
    'content_dir' => dirname(__DIR__) . '/content/pages',
    'session_name' => 'motecms_session',
    'base_path' => '',
    'users_file' => dirname(__DIR__) . '/users.php',
    'log_dir' => dirname(__DIR__) . '/logs',
    'logging_enabled' => true,
    'multi_user_mode' => true,
];
