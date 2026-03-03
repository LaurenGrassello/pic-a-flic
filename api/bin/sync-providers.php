#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Doctrine\DBAL\DriverManager;
use PicaFlic\Infrastructure\Tmdb\TmdbClient;
use PicaFlic\Infrastructure\Tmdb\ProviderCatalogSync;

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$apiKey = getenv('TMDB_API_KEY') ?: ($_ENV['TMDB_API_KEY'] ?? '');

// Prefer DATABASE_URL, else synthesize from DB_* vars.
$dbUrl  = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? '');
if ($dbUrl === '') {
    $host    = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'db');
    $port    = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
    $name    = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'picaflic');
    $user    = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
    $pass    = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');
    $charset = getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? 'utf8mb4');

    $dbUrl = sprintf('mysql://%s:%s@%s:%s/%s?charset=%s',
        rawurlencode($user), rawurlencode($pass),
        $host, $port, $name, $charset
    );
}

if ($apiKey === '') {
    fwrite(STDERR, "Missing TMDB_API_KEY (put it in api/.env)\n");
    exit(1);
}

// --- Doctrine + HTTP setup ---
$config = Setup::createAttributeMetadataConfiguration([__DIR__ . '/../src'], false);
$conn   = DriverManager::getConnection(['url' => $dbUrl]);
$em     = EntityManager::create($conn, $config);

$http  = new \Http\Adapter\Guzzle7\Client(new \GuzzleHttp\Client());
$psr17 = new \Nyholm\Psr7\Factory\Psr17Factory();

$tmdb = new TmdbClient($apiKey, $http, $psr17);
$sync = new ProviderCatalogSync($em, $tmdb, 'US');

echo "[sync] starting...\n";
$providers = $sync->syncProvidersCatalogIndex();
echo "[sync] curated providers returned = " . count($providers) . "\n";
$ids = array_column($providers, 'id');
echo "[sync] curated IDs = " . implode(',', $ids) . "\n";

// Fast seed: last 5 years, monetization = flatrate|free|ads, cap 7 pages/year
$yearNow = (int)date('Y');
$seedFrom = $yearNow;
$seedTo   = $yearNow - 5;

$sync->backfill($ids, false, $seedFrom, $seedTo, 'flatrate|free|ads', 7);
echo "[sync] backfilled movies (seed)\n";

$sync->backfill($ids, true,  $seedFrom, $seedTo, 'flatrate|free|ads', 7);
echo "[sync] backfilled tv (seed)\n";