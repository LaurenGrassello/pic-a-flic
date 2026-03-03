<?php
declare(strict_types=1);

namespace PicaFlic\Infrastructure\Repository;

use Doctrine\DBAL\Query\QueryBuilder;

final class FeedFilters
{
    /** Apply ?providers=netflix|hulu|disney|prime|max|appletv|peacock (and join title_providers only if present). */
    public static function byProviders(QueryBuilder $qb, array $params, string $alias, bool $isTv, string $region='US'): void
    {
        if (empty($params['providers'])) return;

        // 1) Normalize tokens from UI (accept common aliases)
        $names = preg_split('/[|,]/', strtolower((string)$params['providers'])) ?: [];
        $names = array_values(array_filter(array_map('trim', $names)));
        if (!$names) return;

        $tokenMap = [
            'netflix'=>'netflix',
            'disney'=>'disney','disneyplus'=>'disney',
            'hulu'=>'hulu',
            'prime'=>'prime','amazon'=>'prime','amazonprime'=>'prime','amazonprimevideo'=>'prime',
            'max'=>'max','hbomax'=>'max','hbo'=>'max',
            'appletv'=>'appletv','appletvplus'=>'appletv','apple'=>'appletv',
            'peacock'=>'peacock',
        ];
        $tokens = array_unique(array_map(fn($n) => $tokenMap[$n] ?? $n, $names));

        // 2) Map tokens -> provider IDs via fuzzy LIKEs on providers.name
        $conn  = $qb->getConnection();
        $likes = implode(' OR ', array_fill(0, count($tokens), 'LOWER(name) LIKE ?'));
        $args  = array_map(fn($t) => '%'.str_replace(['+',' '], ['',''], $t).'%', $tokens);

        $ids = $conn->fetchFirstColumn("SELECT id FROM providers WHERE $likes", $args);
        if (!$ids) { $qb->andWhere('1=0'); return; }

        // 3) Join title_providers and filter by the (safe) integer ID list
        $tpAlias = 'tp_'.$alias;

        $qb->join(
            $alias,
            'title_providers',
            $tpAlias,
            "{$tpAlias}.tmdb_id = {$alias}.tmdb_id
             AND {$tpAlias}.is_tv = " . ($isTv ? '1' : '0') . "
             AND {$tpAlias}.region = " . $conn->quote($region)
        );

        // Use a literal IN (...) with server-side integers (safe: came from DB above)
        $inList = implode(',', array_map('intval', $ids));
        $qb->andWhere("{$tpAlias}.provider_id IN ($inList)");
    }
}