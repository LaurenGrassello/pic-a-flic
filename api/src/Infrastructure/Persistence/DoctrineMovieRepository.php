<?php
declare(strict_types=1);

namespace PicaFlic\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PicaFlic\Domain\Repository\MovieRepository;

/**
 * DoctrineMovieRepository (DBAL-based)
 *
 * Tables assumed:
 *  - movies(id, tmdb_id, title, release_year, runtime_minutes, ...)
 *  - movie_availability(movie_id, service_id, region)
 *  - streaming_services(id, code, name)
 *  - swipes(user_id, movie_id, liked, created_at)
 *
 * Indexes recommended:
 *  - swipes:               (user_id, movie_id)
 *  - movie_availability:   (region, service_id, movie_id)
 *  - streaming_services:   (code)
 */
final class DoctrineMovieRepository implements MovieRepository
{
    private Connection $conn;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->conn = $em->getConnection();
    }

    /**
     * Base query of available (region/service) movies, excluding already-swiped.
     */
    private function baseQb(int $userId, string $region, ?string $serviceCode = null): \Doctrine\DBAL\Query\QueryBuilder
    {
        $qb = $this->conn->createQueryBuilder();

       $qb->select(
        'DISTINCT m.id',
        'm.tmdb_id          AS tmdbId',
        'm.title            AS title',
        'm.release_year     AS releaseYear',
        'm.runtime_minutes  AS runtimeMinutes',
        'm.poster_path      AS posterPath',
        'm.overview         AS overview'
        )

            ->from('movies', 'm')
            ->innerJoin('m', 'movie_availability', 'a', 'a.movie_id = m.id AND a.region = :region')
            ->innerJoin('a', 'streaming_services', 's', 's.id = a.service_id')
            ->leftJoin('m', 'swipes', 'sw', 'sw.movie_id = m.id AND sw.user_id = :uid')
            ->where('sw.movie_id IS NULL')
            ->setParameter('region', $region)
            ->setParameter('uid', $userId, \PDO::PARAM_INT);

        if ($serviceCode !== null && $serviceCode !== '') {
            $qb->andWhere('s.code = :svc')->setParameter('svc', $serviceCode);
        }

        return $qb;
    }

    /**
     * Deck (unswiped, newest first). Optional cursor: $afterId for pagination.
     */
    public function deck(
        int $userId,
        string $region,
        ?string $serviceCode,
        int $limit = 20,
        ?int $afterId = null
    ): array {
        $qb = $this->baseQb($userId, $region, $serviceCode)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if ($afterId !== null) {
            $qb->andWhere('m.id < :afterId')->setParameter('afterId', $afterId, \PDO::PARAM_INT);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * For You (placeholder = newest-first; refine with taste later).
     */
    public function forYou(
        int $userId,
        string $region,
        ?string $serviceCode,
        int $limit = 20,
        ?int $afterId = null
    ): array {
        $qb = $this->baseQb($userId, $region, $serviceCode)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if ($afterId !== null) {
            $qb->andWhere('m.id < :afterId')->setParameter('afterId', $afterId, \PDO::PARAM_INT);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Challenge Me: deterministic “random” pick using SHA2-based sort key.
     */
    public function challengePick(
        int $userId,
        string $region,
        ?string $serviceCode
    ): ?array {
        $seed = (new \DateTimeImmutable('now'))->format('Y-m-d') . ':' . $userId;

        $qb = $this->baseQb($userId, $region, $serviceCode)
            ->addSelect("SHA2(CONCAT(m.id, :seed), 256) AS HSort")
            ->setParameter('seed', $seed)
            ->orderBy('HSort', 'ASC')
            ->setMaxResults(1);

        $row = $qb->executeQuery()->fetchAssociative();
        return $row ?: null;
    }
}