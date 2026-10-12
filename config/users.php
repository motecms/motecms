<?php
declare(strict_types=1);

// Default admin user - change password after first login!
return [
    'admin' => [
        'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
        'role' => 'admin',
        'name' => 'Administrator'
    ]
];
