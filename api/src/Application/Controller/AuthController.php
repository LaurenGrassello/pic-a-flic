<?php
declare(strict_types=1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PicaFlic\Domain\Entity\User;
use PicaFlic\Domain\Entity\StreamingService;
use PicaFlic\Domain\Entity\UserStreamingService;
use PicaFlic\Infrastructure\Security\JwtService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use DateTimeImmutable;

final class AuthController
{
    public function __construct(
        private EntityManagerInterface $em,
        private JwtService $jwt,
        private \PicaFlic\Infrastructure\Service\MailService $mailer
    ) {}

    /** ---------- Helpers ---------- */

    private function json(Response $res, array $payload, int $status = 200): Response {
        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function validatePayload(array $d): ?string {
        if (!filter_var(trim($d['email'] ?? ''), FILTER_VALIDATE_EMAIL)) return 'Invalid email';
        if (strlen($d['password'] ?? '') < 6) return 'Password must be at least 6 chars';
        if (trim($d['display_name'] ?? '') === '') return 'Display name is required';
        return null;
    }

    private function accessTtl(): int {
        // Demo-friendly long TTL; override with env if provided
        return (int)($_ENV['JWT_TTL'] ?? $this->jwt->getTtl() ?? 8 * 60 * 60);
    }

    private function refreshTtl(): int {
        // Default 14 days; override via env JWT_REFRESH_TTL seconds
        return (int)($_ENV['JWT_REFRESH_TTL'] ?? 14 * 24 * 60 * 60);
    }

    private function newRefreshRaw(): string {
        return bin2hex(random_bytes(32)); // 64 hex chars
    }

    private function hashRefresh(string $raw): string {
        return hash('sha256', $raw);
    }

    private function insertRefreshToken(int $userId, string $hash, DateTimeImmutable $expires): void {
        $conn = $this->em->getConnection();
        $conn->insert('refresh_tokens', [
            'user_id'    => $userId,
            'token_hash' => $hash,
            'expires_at' => $expires->format('Y-m-d H:i:s'),
            'revoked'    => 0,
        ], [
            'user_id'    => \PDO::PARAM_INT,
            'token_hash' => \PDO::PARAM_STR,
            'expires_at' => \PDO::PARAM_STR,
            'revoked'    => \PDO::PARAM_INT,
        ]);
    }

    private function fetchRefreshRow(string $hash): ?array {
        $conn = $this->em->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT * FROM refresh_tokens WHERE token_hash = ? AND revoked = 0 LIMIT 1',
            [$hash]
        );
        return $row ?: null;
    }

    private function rotateRefreshRow(string $oldHash, string $newHash, DateTimeImmutable $newExp): void {
        $conn = $this->em->getConnection();
        $conn->update('refresh_tokens',
            ['token_hash' => $newHash, 'expires_at' => $newExp->format('Y-m-d H:i:s')],
            ['token_hash' => $oldHash]
        );
    }

    private function revokeRefreshRow(string $hash): void {
        $conn = $this->em->getConnection();
        $conn->update('refresh_tokens', ['revoked' => 1], ['token_hash' => $hash]);
    }

    /** ---------- Endpoints ---------- */

    /** POST /auth/register */
    public function register(Request $req, Response $res): Response {
        $d = (array)($req->getParsedBody() ?? []);
        if ($err = $this->validatePayload($d)) {
            return $this->json($res, ['error' => $err], 422);
        }
        $email = trim($d['email']);
        $display = trim($d['display_name']);
        $services = is_array($d['services'] ?? null) ? $d['services'] : [];

        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json($res, ['error' => 'Email already registered'], 409);
        }

        $user = new User($email, password_hash($d['password'], PASSWORD_ARGON2ID), $display);
        $this->em->persist($user);

        if ($services) {
            $svcRepo = $this->em->getRepository(StreamingService::class);
            foreach ($services as $code) {
                if ($svc = $svcRepo->findOneBy(['code' => $code])) {
                    $this->em->persist(new UserStreamingService($user, $svc));
                }
            }
        }

        $this->em->flush();

        return $this->json($res, [
            'id'           => $user->getId(),
            'email'        => $user->getEmail(),
            'display_name' => $user->getDisplayName()
        ], 201);
    }

    /** POST /auth/login  -> { token, refresh_token, expires_in } */
    public function login(Request $req, Response $res): Response {
        $d = (array)($req->getParsedBody() ?? []);
        $email = trim($d['email'] ?? '');
        $password = $d['password'] ?? '';

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user || !password_verify($password, $user->getPasswordHash())) {
            return $this->json($res, ['error' => 'Invalid credentials'], 401);
        }

        $accessTtl  = $this->accessTtl();
        $refreshTtl = $this->refreshTtl();

        // Access token (JWT)
        $token = $this->jwt->issue($user->getId(), $accessTtl);

        // Refresh token (opaque random)
        $refreshRaw  = $this->newRefreshRaw();
        $refreshHash = $this->hashRefresh($refreshRaw);
        $this->insertRefreshToken($user->getId(), $refreshHash, (new DateTimeImmutable())->modify("+{$refreshTtl} seconds"));

        return $this->json($res, [
            'token'         => $token,
            'refresh_token' => $refreshRaw,
            'expires_in'    => $accessTtl,
        ]);
    }

    // POST /auth/forgot  {email}
    public function forgot(Request $req, Response $res): Response {
    $d = (array)($req->getParsedBody() ?? []);
    $email = trim((string)($d['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->json($res, ['error'=>'Invalid email'], 422);
    }

    $user = $this->em->getRepository(User::class)->findOneBy(['email'=>$email]);
    if (!$user) return $this->json($res, ['ok'=>true]); // don’t leak existence

    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $this->em->getConnection()->insert('password_resets', [
        'user_id'    => $user->getId(),
        'token_hash' => $hash,
        'expires_at' => (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'),
    ]);

    $link = ($_ENV['WEB_BASE_URL'] ?? 'http://localhost:5173')."/reset?token=$raw";
    $html = "<p>Click to reset your password:</p><p><a href=\"$link\">$link</a></p>";
    $this->mailer->send($email, 'Reset your password', $html);

    return $this->json($res, ['ok'=>true]);
    }

    // POST /auth/reset {token, new_password}
    public function reset(Request $req, Response $res): Response {
    $d = (array)($req->getParsedBody() ?? []);
    $token = (string)($d['token'] ?? '');
    $new   = (string)($d['new_password'] ?? '');
    
    if (strlen($new) < 6 || $token === '') {
        return $this->json($res, ['error'=>'Invalid payload'], 422);
    }

    $hash = hash('sha256', $token);
    $conn = $this->em->getConnection();
    $row = $conn->fetchAssociative(
        "SELECT * FROM password_resets
        WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1", [$hash]
    );
    if (!$row) return $this->json($res, ['error'=>'Invalid or expired token'], 400);

    /** @var User|null $user */
    $user = $this->em->find(User::class, (int)$row['user_id']);
    if (!$user) return $this->json($res, ['error'=>'User not found'], 404);

    $user->setPasswordHash(password_hash($new, PASSWORD_ARGON2ID));
    $this->em->flush();
    $conn->update('password_resets', ['used_at'=> (new \DateTimeImmutable())->format('Y-m-d H:i:s')], ['id'=>$row['id']]);

    return $this->json($res, ['ok'=>true]);
    }

    /** POST /auth/refresh  { refresh_token } -> rotates and returns new pair */
    public function refresh(Request $req, Response $res): Response {
        $d = (array)($req->getParsedBody() ?? []);
        $raw = $d['refresh_token'] ?? '';
        if (!$raw) return $this->json($res, ['error' => 'missing refresh_token'], 400);

        $hash = $this->hashRefresh($raw);
        $row = $this->fetchRefreshRow($hash);
        if (!$row) return $this->json($res, ['error' => 'invalid or revoked refresh token'], 401);

        if (new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable()) {
            return $this->json($res, ['error' => 'refresh token expired'], 401);
        }

        $userId = (int)$row['user_id'];
        $accessTtl  = $this->accessTtl();
        $refreshTtl = $this->refreshTtl();

        // Mint new access token
        $newAccess = $this->jwt->issue($userId, $accessTtl);

        // Rotate refresh token
        $newRaw  = $this->newRefreshRaw();
        $newHash = $this->hashRefresh($newRaw);
        $this->rotateRefreshRow($hash, $newHash, (new DateTimeImmutable())->modify("+{$refreshTtl} seconds"));

        return $this->json($res, [
            'token'         => $newAccess,
            'refresh_token' => $newRaw,
            'expires_in'    => $accessTtl,
        ]);
    }

    /** POST /auth/logout  { refresh_token } -> revokes it */
    public function logout(Request $req, Response $res): Response {
        $d = (array)($req->getParsedBody() ?? []);
        $raw = $d['refresh_token'] ?? '';
        if ($raw) {
            $this->revokeRefreshRow($this->hashRefresh($raw));
        }
        return $this->json($res, ['ok' => true]);
    }

    /** GET /auth/me */
    public function me(Request $req, Response $res): Response {
        $uid = (int)($req->getAttribute('uid') ?? 0);
        if (!$uid) return $this->json($res, ['error' => 'Unauthorized'], 401);

        $user = $this->em->find(User::class, $uid);
        if (!$user) return $this->json($res, ['error' => 'Not found'], 404);

        return $this->json($res, [
            'id'           => $user->getId(),
            'email'        => $user->getEmail(),
            'display_name' => $user->getDisplayName()
        ]);
    }
}