<?php
declare (strict_types = 1);

use Doctrine\ORM\EntityManager;
use PicaFlic\Application\Controller\AuthController;
use PicaFlic\Application\Controller\QuizController;
use PicaFlic\Application\Controller\SocialController;
use PicaFlic\Application\Middleware\JwtMiddleware;
use PicaFlic\Bootstrap\AppBuilder;
use PicaFlic\Infrastructure\Security\JwtService;
use PicaFlic\Infrastructure\Service\MailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

/**
 * pic-a-flic — Slim 4 front controller
 */

// ---------------------------------------------------------
// Bootstrap container & Slim app
// ---------------------------------------------------------
$container = AppBuilder::buildContainer(dirname(__DIR__));
AppFactory::setContainer($container);
$app = AppFactory::create();

// Redirect root to live API docs
$app->get('/', fn($req, $res) =>
    $res->withHeader('Location', '/docs.html')->withStatus(302)
);

// Parse JSON / form bodies
$app->addBodyParsingMiddleware();

// ---------------------------------------------------------
// CORS (allowlist from env: CORS_ALLOW_ORIGINS=a,b,c)
// ---------------------------------------------------------
$allowed = array_values(array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOW_ORIGINS'] ?? 'http://localhost:3000,http://localhost:5173,http://localhost:5174,http://localhost:5175'))));

$app->add(function (Request $req, $handler) use ($allowed) {
    $origin = $req->getHeaderLine('Origin');
    $res = $handler->handle($req);

    // Only reflect the origin if it is explicitly allowed
    if ($origin && in_array($origin, $allowed, true)) {
        $res = $res
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }

    return $res
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');
});

// Preflight
$app->options('/{routes:.+}', fn($req, $res) => $res->withStatus(204));

// ---------------------------------------------------------
// Shared services
// ---------------------------------------------------------
$settings = $container->get('settings');

/** @var EntityManager $em */
$em = $container->get(EntityManager::class);

// JWT (access token)
$jwt = new JwtService($settings['jwt']['secret'], $settings['jwt']['ttl']);
$authMw = new JwtMiddleware($jwt);

// ---------------------------------------------------------
// Admin gate (X-Admin-Key)
// ---------------------------------------------------------
$adminKey = $_ENV['ADMIN_API_KEY'] ?? null;
$adminGate = function (Request $req, $handler) use ($adminKey, $app) {
    if (!$adminKey || ($req->getHeaderLine('X-Admin-Key') !== $adminKey)) {
        $res = $app->getResponseFactory()->createResponse(401);
        $res->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $res->withHeader('Content-Type', 'application/json');
    }
    return $handler->handle($req);
};

// ---------------------------------------------------------
// Health
// ---------------------------------------------------------
$app->get('/health', function ($req, $res) use ($container) {
    $em = $container->get(\Doctrine\ORM\EntityManager::class);
    $conn = $em->getConnection();
    $db = $conn->fetchOne('SELECT DATABASE()');
    $host = $conn->getParams()['host'] ?? 'n/a';

    $payload = ['ok' => true, 'env' => $_ENV['APP_ENV'] ?? 'dev', 'db' => ['host' => $host, 'name' => $db]];
    $res->getBody()->write(json_encode($payload));
    return $res->withHeader('Content-Type', 'application/json');
});

// ---------------------------------------------------------
// Admin (ingest stub)
// ---------------------------------------------------------
$admin = $container->get(\PicaFlic\Application\Controller\AdminController::class);
$app->post('/admin/ingest-tmdb', [$admin, 'ingestTmdb'])->add($adminGate);

// ---------------------------------------------------------
// Auth
// ---------------------------------------------------------
$mailer = $container->get(MailService::class);
$auth = new AuthController($em, $jwt, $mailer);
$app->post('/auth/register', [$auth, 'register']);
$app->post('/auth/login', [$auth, 'login']);
$app->get('/auth/me', [$auth, 'me'])->add($authMw);
$app->post('/auth/refresh', [$auth, 'refresh']);
$app->post('/auth/logout', [$auth, 'logout']);
$app->post('/auth/forgot', [$auth, 'forgot']);
$app->post('/auth/reset', [$auth, 'reset']);

// Profile
$profile = $container->get(\PicaFlic\Application\Controller\ProfileController::class);
$app->get('/profile', [$profile, 'getProfile'])->add($authMw);
$app->put('/profile/password', [$profile, 'changePassword'])->add($authMw);
$app->put('/profile/services', [$profile, 'setServices'])->add($authMw);

// ---------------------------------------------------------
// Quiz
// ---------------------------------------------------------
$quiz = new QuizController();
$app->get('/quiz', [$quiz, 'get']);
$app->post('/quiz/submit', [$quiz, 'submit'])->add($authMw);

// ---------------------------------------------------------
// Social
// ---------------------------------------------------------
$social = new SocialController($em);
$app->post('/social/follow/{userId}', [$social, 'follow'])->add($authMw);
$app->delete('/social/follow/{userId}', [$social, 'unfollow'])->add($authMw);
$app->post('/social/swipe', [$social, 'swipe'])->add($authMw);
$app->get('/social/matches/{friendId}', [$social, 'matches'])->add($authMw);
$app->get('/social/users/search', [$social, 'searchUsers'])->add($authMw);
$app->get('/social/friends', [$social, 'friends'])->add($authMw);
$app->post('/social/friends/request/{userId}', [$social, 'requestFriend'])->add($authMw);
$app->post('/social/friends/accept/{userId}', [$social, 'acceptFriend'])->add($authMw);
$app->delete('/social/friends/{userId}', [$social, 'removeFriend'])->add($authMw);

// ---------------------------------------------------------
// Feed (lazy resolve MovieRepository so /health doesn’t hit DB)
// ---------------------------------------------------------
// Deck
$feed = $container->get(\PicaFlic\Application\Controller\FeedController::class);

$app->get('/search', [$feed, 'search'])->add($authMw);
$app->get('/title/{kind}/{id}/providers', [$feed, 'titleProviders'])->add($authMw);
$app->get('/titles/{kind}/{tmdbId}', [$feed, 'details'])->add($authMw);

$app->get('/feed/deck', [$feed, 'deck'])->add($authMw);
$app->get('/feed/for-you', [$feed, 'forYou'])->add($authMw);
$app->get('/feed/challenge-me', [$feed, 'challenge'])->add($authMw);

// ---------------------------------------------------------
// JSON error handler (must also add CORS headers)
// ---------------------------------------------------------
$customErrorHandler = function (
    Request $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app, $allowed): Response {
    $response = $app->getResponseFactory()->createResponse(500);

    $payload = ['error' => $exception->getMessage()];
    $response->getBody()->write(json_encode($payload));
    $response = $response->withHeader('Content-Type', 'application/json');

    // CORS on error responses too
    $origin = $request->getHeaderLine('Origin');
    if ($origin && in_array($origin, $allowed, true)) {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');
    }

    return $response;
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

// ---------------------------------------------------------
$app->run();