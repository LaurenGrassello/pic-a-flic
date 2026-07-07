<?php
declare(strict_types=1);

$dsn = $_ENV['DATABASE_URL'] ?? $_ENV['DB_DSN'] ?? null;

if ($dsn) {
    return ['url' => $dsn];
}

return [
    'driver'   => 'pdo_mysql',
    'host'     => $_ENV['DB_HOST']  ?? 'db',
    'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
    'dbname'   => $_ENV['DB_NAME']  ?? 'picaflic',
    'user'     => $_ENV['DB_USER']  ?? 'root',
    'password' => $_ENV['DB_PASS']  ?? 'password',
    'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];
