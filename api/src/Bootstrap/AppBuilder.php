<?php
declare(strict_types=1);

namespace PicaFlic\Bootstrap;

use DI\ContainerBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use PicaFlic\Infrastructure\Tmdb\TmdbClient;
use PicaFlic\Domain\Repository\MovieRepository;
use PicaFlic\Infrastructure\Persistence\DoctrineMovieRepository;
use PicaFlic\Application\Controller\AdminController;
use PicaFlic\Infrastructure\Service\MailService;
use function DI\autowire;

// PSR-18 + PSR-17 impls
use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle7\Client as GuzzlePsr18Adapter;
use Nyholm\Psr7\Factory\Psr17Factory;

final class AppBuilder
{
    public static function buildContainer(string $basePath): \DI\Container
    {
        // Load environment (.env) if present
        if (is_file($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->load();
        }

        $debug = filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOL);

        $builder = new ContainerBuilder();

        $builder->addDefinitions([

            MailService::class => static fn() => new MailService($_ENV['APP_ENV'] ?? 'dev'),

            // -------------------------
            // App settings
            // -------------------------
            'settings' => static function () {
                return [
                    'env'   => $_ENV['APP_ENV']  ?? 'dev',
                    'debug' => (bool)($_ENV['APP_DEBUG'] ?? true),

                    'db' => [
                        'dsn'     => $_ENV['DATABASE_URL'] ?? $_ENV['DB_DSN'] ?? null, // prefer DATABASE_URL if set
                        'host'    => $_ENV['DB_HOST']  ?? 'db',
                        'port'    => (int)($_ENV['DB_PORT'] ?? 3306),
                        'name'    => $_ENV['DB_NAME']  ?? 'picaflic',
                        'user'    => $_ENV['DB_USER']  ?? 'root',
                        'pass'    => $_ENV['DB_PASS']  ?? 'password',
                        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                    ],

                    'jwt' => [
                        'secret'       => $_ENV['JWT_SECRET']           ?? 'dev_secret_change_me',
                        'ttl'          => (int)($_ENV['JWT_TTL']        ?? 8 * 60 * 60),            // 8h
                        'refresh_ttl'  => (int)($_ENV['JWT_REFRESH_TTL'] ?? 14 * 24 * 60 * 60),    // 14d
                    ],
                ];
            },

            // -------------------------
            // Logger (stdout)
            // -------------------------
            LoggerInterface::class => static function () {
                $log = new Logger('pic-a-flic');
                $log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
                return $log;
            },

            // -------------------------
            // Doctrine EntityManager
            // -------------------------
            EntityManager::class => static function (ContainerInterface $c) use ($basePath, $debug) {
                $entityPaths = [$basePath . '/src/Domain/Entity'];

                $config = Setup::createAttributeMetadataConfiguration(
                    $entityPaths,
                    $debug,
                    null,
                    null,
                    false
                );

                $db  = $c->get('settings')['db'];
                $dsn = $db['dsn'] ?? null;

                if (is_string($dsn) && $dsn !== '' && str_contains($dsn, '://')) {
                    $conn = ['url' => $dsn]; // Doctrine URL form (DATABASE_URL)
                } else {
                    $conn = [
                        'driver'   => 'pdo_mysql',
                        'host'     => $db['host'],
                        'port'     => $db['port'],
                        'dbname'   => $db['name'],
                        'user'     => $db['user'],
                        'password' => $db['pass'],
                        'charset'  => $db['charset'],
                        'driverOptions' => [
                            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $db['charset'],
                        ],
                    ];
                }

                return EntityManager::create($conn, $config);
            },

            EntityManagerInterface::class => \DI\get(EntityManager::class),

            // -------------------------
            // TMDb client (PSR-18/PSR-17)
            // -------------------------
            TmdbClient::class => static function () {
    $v3 = $_ENV['TMDB_API_KEY'] ?? getenv('TMDB_API_KEY') ?: '';
    if ($v3 === '') {
        throw new \RuntimeException('TMDB_API_KEY missing (set in api/.env)');
    }

    // PSR-18 HTTP client + PSR-17 factories
    $http  = new GuzzlePsr18Adapter(new GuzzleClient());
    $psr17 = new Psr17Factory();

    return new TmdbClient($v3, $http, $psr17);
},

            // -------------------------
            // Domain repository bindings
            // -------------------------
            MovieRepository::class => autowire(DoctrineMovieRepository::class),

            AdminController::class => autowire(),
        ]);

        return $builder->build();
    }
}