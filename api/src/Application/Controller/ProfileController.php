<?php
declare(strict_types=1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PicaFlic\Domain\Entity\User;
use PicaFlic\Domain\Entity\Provider;
use PicaFlic\Domain\Entity\UserProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProfileController
{
    public function __construct(private EntityManagerInterface $em) {}

    private function json(Response $res, array $payload, int $status = 200): Response {
        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /** GET /profile */
    public function getProfile(Request $req, Response $res): Response {
        $uid = (int)($req->getAttribute('uid') ?? 0);
        if (!$uid) return $this->json($res, ['error'=>'Unauthorized'], 401);

        /** @var User|null $user */
        $user = $this->em->find(User::class, $uid);
        if (!$user) return $this->json($res, ['error'=>'Not found'], 404);

        // Load selected provider IDs (via join table)
        $conn = $this->em->getConnection();
        $provIds = $conn->fetchFirstColumn(
            'SELECT provider_id FROM user_providers WHERE user_id = ?',
            [$uid]
        );

        // Optionally include provider names
        $providers = [];
        if ($provIds) {
            $rows = $conn->fetchAllAssociative(
                'SELECT id, name FROM providers WHERE id IN (?)',
                [$provIds],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
            );
            $providers = array_map(fn($r)=>['id'=>(int)$r['id'], 'name'=>$r['name']], $rows);
        }

        return $this->json($res, [
            'id'           => $user->getId(),
            'email'        => $user->getEmail(),
            'display_name' => $user->getDisplayName(),
            'providers'    => $providers,
        ]);
    }

    /** PUT /profile/password  { current_password, new_password } */
    public function changePassword(Request $req, Response $res): Response {
        $uid = (int)($req->getAttribute('uid') ?? 0);
        if (!$uid) return $this->json($res, ['error'=>'Unauthorized'], 401);

        $body = (array)($req->getParsedBody() ?? []);
        $current = (string)($body['current_password'] ?? '');
        $next    = (string)($body['new_password'] ?? '');

        /** @var User|null $user */
        $user = $this->em->find(User::class, $uid);
        if (!$user) return $this->json($res, ['error'=>'Not found'], 404);

        if (!$current || !$next || strlen($next) < 6) {
            return $this->json($res, ['error'=>'Invalid payload'], 422);
        }
        if (!password_verify($current, $user->getPasswordHash())) {
            return $this->json($res, ['error'=>'Current password is incorrect'], 400);
        }

        $user->setPasswordHash(password_hash($next, PASSWORD_ARGON2ID));
        $this->em->flush();

        return $this->json($res, ['ok'=>true]);
    }

    /** PUT /profile/services  { provider_ids: number[] } */
    public function setServices(Request $req, Response $res): Response {
        $uid = (int)($req->getAttribute('uid') ?? 0);
        if (!$uid) return $this->json($res, ['error'=>'Unauthorized'], 401);

        $body = (array)($req->getParsedBody() ?? []);
        $ids  = array_values(array_filter(array_map('intval', (array)($body['provider_ids'] ?? []))));

        /** @var User|null $user */
        $user = $this->em->find(User::class, $uid);
        if (!$user) return $this->json($res, ['error'=>'Not found'], 404);

        // Clear existing
        $conn = $this->em->getConnection();
        $conn->delete('user_providers', ['user_id' => $uid]);

        // Insert new (via Doctrine)
        if ($ids) {
            $repo = $this->em->getRepository(Provider::class);
            foreach ($ids as $pid) {
                $prov = $repo->find($pid);
                if ($prov) {
                    $this->em->persist(new UserProvider($user, $prov));
                }
            }
            $this->em->flush();
        }

        return $this->json($res, ['ok'=>true, 'count'=>count($ids)]);
    }
}