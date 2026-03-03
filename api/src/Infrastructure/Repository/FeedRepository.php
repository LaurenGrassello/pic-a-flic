<?php
declare(strict_types=1);

namespace PicaFlic\Infrastructure\Repository;

use Doctrine\DBAL\Connection;
use PicaFlic\Infrastructure\Repository\FeedFilters;

final class FeedRepository
{
    public function __construct(private Connection $conn) {}

    public function forYou(array $params): array
    {
        $limit  = min(max((int)($params['limit'] ?? 40), 1), 100);
        $page   = max((int)($params['page'] ?? 1), 1);
        $offset = ($page - 1) * $limit;

        // movies
        $qm = $this->conn->createQueryBuilder()
            ->select('m.*')
            ->from('movies', 'm')
            ->orderBy('m.popularity', 'DESC');
        FeedFilters::byProviders($qm, $params, 'm', false);

        // tv
        $qt = $this->conn->createQueryBuilder()
            ->select('t.*')
            ->from('tv_shows', 't')
            ->orderBy('t.popularity', 'DESC');
        FeedFilters::byProviders($qt, $params, 't', true);

        // union + paging
        $sql  = sprintf(
            '(%s) UNION ALL (%s) ORDER BY popularity DESC LIMIT %d OFFSET %d',
            $qm->getSQL(), $qt->getSQL(), $limit, $offset
        );
        return $this->conn->fetchAllAssociative(
            $sql,
            array_merge($qm->getParameters(), $qt->getParameters())
        );
    }
}