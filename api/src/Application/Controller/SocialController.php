<?php
declare(strict_types=1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManager;
use PicaFlic\Domain\Entity\User;
use PicaFlic\Domain\Entity\Follow;
use PicaFlic\Domain\Entity\Swipe;
use PicaFlic\Domain\Entity\Movie;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Social features: follow/unfollow, swipe, and matches.
 */
final class SocialController
{
    public function __construct(private EntityManager $em) {}

    /** POST /social/follow/{userId} */
    public function follow(Request $req, Response $res, array $args): Response
    {
        $meId = (int)$req->getAttribute('uid');
        $targetId = (int)($args['userId'] ?? 0);
        if ($meId <= 0 || $targetId <= 0 || $meId === $targetId) {
            return $this->json($res, ['error' => 'Invalid follow target'], 422);
        }

        $me = $this->em->find(User::class, $meId);
        $target = $this->em->find(User::class, $targetId);
        if (!$me || !$target) return $this->json($res, ['error'=>'User not found'], 404);

        // prevent duplicates
        $exists = $this->em->getRepository(Follow::class)->findOneBy(['follower'=>$me, 'followee'=>$target]);
        if (!$exists) {
            $this->em->persist(new Follow($me, $target));
            $this->em->flush();
        }
        return $this->json($res, ['ok'=>true]);
    }

    /** DELETE /social/follow/{userId} */
    public function unfollow(Request $req, Response $res, array $args): Response
    {
        $meId = (int)$req->getAttribute('uid');
        $targetId = (int)($args['userId'] ?? 0);
        $me = $this->em->find(User::class, $meId);
        $target = $this->em->find(User::class, $targetId);
        if (!$me || !$target) return $this->json($res, ['error'=>'User not found'], 404);

        $row = $this->em->getRepository(Follow::class)->findOneBy(['follower'=>$me, 'followee'=>$target]);
        if ($row) { $this->em->remove($row); $this->em->flush(); }
        return $this->json($res, ['ok'=>true]);
    }

    /** POST /social/swipe  body: { "movie_id": 123, "liked": true } */
    public function swipe(Request $req, Response $res): Response
    {
        $meId = (int)$req->getAttribute('uid');
        $data = json_decode((string)$req->getBody(), true) ?: [];
        $movieId = (int)($data['movie_id'] ?? 0);
        $liked = (bool)($data['liked'] ?? false);

        $me = $this->em->find(User::class, $meId);
        $movie = $this->em->find(Movie::class, $movieId);
        if (!$me || !$movie) return $this->json($res, ['error'=>'Not found'], 404);

        // upsert: one swipe per user+movie
        $repo = $this->em->getRepository(Swipe::class);
        $existing = $repo->findOneBy(['user'=>$me, 'movie'=>$movie]);
        if ($existing) {
            // reflect new choice
            $refLiked = new \ReflectionProperty(Swipe::class, 'liked');
            $refLiked->setAccessible(true);
            $refLiked->setValue($existing, $liked);
        } else {
            $this->em->persist(new Swipe($me, $movie, $liked));
        }
        $this->em->flush();

        return $this->json($res, ['ok'=>true]);
    }

    /**
     * GET /social/matches/{friendId}?limit=20&offset=0
     * Returns movies both users liked.
     */
    public function matches(Request $req, Response $res, array $args): Response
    {
        $meId = (int)$req->getAttribute('uid');
        $friendId = (int)($args['friendId'] ?? 0);
        $q = $req->getQueryParams();
        $limit  = max(1, min(100, (int)($q['limit'] ?? 20)));
        $offset = max(0, (int)($q['offset'] ?? 0));

        $qb = $this->em->createQueryBuilder();
        $qb->select('m.id AS id, m.tmdbId AS tmdbId, m.title AS title')
           ->from(Swipe::class, 's1')
           ->join('s1.movie', 'm')
           ->join(Swipe::class, 's2', 'WITH', 's2.movie = m AND s2.liked = true')
           ->where('s1.user = :me AND s1.liked = true AND s2.user = :friend')
           ->groupBy('m.id')
           ->setFirstResult($offset)
           ->setMaxResults($limit)
           ->setParameter('me', $meId)
           ->setParameter('friend', $friendId);

        $rows = $qb->getQuery()->getArrayResult();
        return $this->json($res, ['results'=>$rows, 'count'=>count($rows)]);
    }

    private function json(Response $res, array $payload, int $status=200): Response
    {
        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type','application/json')->withStatus($status);
    }
}