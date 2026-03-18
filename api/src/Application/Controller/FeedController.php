<?php
declare (strict_types = 1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PicaFlic\Infrastructure\Repository\FeedFilters;
use PicaFlic\Infrastructure\Tmdb\TmdbClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FeedController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TmdbClient $tmdb,
    ) {}

    private function json(Response $res, $payload, int $status = 200): Response
    {
        $res->getBody()->write(is_string($payload) ? $payload : json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /** GET /feed/for-you?limit=60&page=1&providers=netflix|disney */
    public function forYou(Request $req, Response $res): Response
    {
        return $this->mixedFeed($req, $res, order: 'popularity');
    }

    /** GET /feed/deck ... (newer first) */
    public function deck(Request $req, Response $res): Response
    {
        return $this->mixedFeed($req, $res, order: 'release');
    }

    /** GET /feed/challenge-me — return ONE random pick, max 3/day per user */
    public function challenge(Request $req, Response $res): Response
    {
        // --- enforce per-user quota ---
        $uid = (int) ($req->getAttribute('uid') ?? 0);
        if (!$uid) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $count = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM challenge_uses WHERE user_id = ? AND used_at >= (NOW() - INTERVAL 1 DAY)",
            [$uid]
        );
        if ($count >= 3) {
            // CORS on errors already handled by your error middleware
            return $this->json($res, [
                'error' => 'Challenge limit reached',
                'limit' => 3,
                'window_hours' => 24,
            ], 429);
        }

        // record this use
        $conn->insert('challenge_uses', ['user_id' => $uid]);

        // --- pick ONE random item across all services ---
        $row = $this->challengeOne($req);

        // keep response shape as an array (with a single item) so UI code stays simple
        return $this->json($res, $row ? [$row] : []);
    }

    /** Randomly pick ONE item via TMDB discover (no provider filters; all services). */
    private function challengeOne(Request $req): ?array
    {
        $params = $req->getQueryParams();
        $region = $params['region'] ?? 'US';

        // flip a coin: movie or tv
        $types = ['movie', 'tv'];
        shuffle($types);

        foreach ($types as $type) {
            // 1) first page fetch only to read total_pages
            $first = $this->tmdb->discover($type, [
                'watch_region' => $region,
                'include_adult' => 'false',
                'sort_by' => 'popularity.desc',
                'page' => 1,
            ]);
            $totalPages = max(1, min(500, (int) ($first['total_pages'] ?? 1))); // TMDB caps at 500

            // 2) pick a random page, fetch it
            $randPage = random_int(1, $totalPages);
            $data = ($randPage === 1) ? $first : $this->tmdb->discover($type, [
                'watch_region' => $region,
                'include_adult' => 'false',
                'sort_by' => 'popularity.desc',
                'page' => $randPage,
            ]);

            $results = $data['results'] ?? [];
            if (!$results) {
                continue;
            }

            // 3) pick a random result on that page
            $pick = $results[random_int(0, count($results) - 1)] ?? null;
            if (!$pick) {
                continue;
            }

            $tmdbId = (int) ($pick['id'] ?? 0);

            return [
                'id' => $this->resolveLocalId($type, $tmdbId),
                'tmdb_id' => $tmdbId,
                'is_tv' => $type === 'tv' ? 1 : 0,
                'title' => $type === 'tv' ? ($pick['name'] ?? '') : ($pick['title'] ?? ''),
                'popularity' => (float) ($pick['popularity'] ?? 0),
                'release_date' => $type === 'tv' ? ($pick['first_air_date'] ?? null) : ($pick['release_date'] ?? null),
                'poster_path' => $pick['poster_path'] ?? null,
            ];
        }

        return null;
    }

    private function resolveLocalId(string $type, int $tmdbId): ?int
    {
        if ($tmdbId <= 0) {
            return null;
        }

        $conn = $this->em->getConnection();
        $table = $type === 'tv' ? 'tv_shows' : 'movies';

        try {
            $id = $conn->fetchOne(
                "SELECT id FROM {$table} WHERE tmdb_id = ? LIMIT 1",
                [$tmdbId]
            );

            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Map UI tokens to exact provider names, then fetch IDs. */
    private function resolveProviderIds(array $tokens): array
    {
        if (!$tokens) {
            return [];
        }

        $map = [
            'netflix' => ['Netflix'],
            'prime' => ['Amazon Prime Video'],
            'hulu' => ['Hulu'],
            'disney' => ['Disney Plus'],
            'max' => ['Max', 'HBO Max'], // you currently have “HBO Max”
            'appletv' => ['Apple TV+'],
            'peacock' => ['Peacock Premium', 'Peacock Premium Plus'],
        ];
        $names = [];
        foreach ($tokens as $t) {
            $t = strtolower(trim($t));
            if (isset($map[$t])) {$names = array_merge($names, $map[$t]);}
        }
        $names = array_values(array_unique($names));
        if (!$names) {
            return [];
        }

        $conn = $this->em->getConnection();
        $place = implode(',', array_fill(0, count($names), '?'));
        $ids = $conn->fetchFirstColumn("SELECT id FROM providers WHERE name IN ($place)", $names);
        return array_map('intval', $ids);
    }

    /**
     * Shared builder for movie+tv union with provider filter + paging.
     * $order: 'popularity' | 'release' | 'random'
     */
    private function mixedFeed(Request $req, Response $res, string $order = 'popularity'): Response
    {
        $params = $req->getQueryParams();

        // Search passthrough: if q is present, use the search pipeline
        $q = trim((string) ($params['q'] ?? ''));
        if ($q !== '') {
            return $this->searchFeed($req, $res);
        }

        // Back-compat for old param
        if (!isset($params['providers']) && isset($params['service']) && $params['service'] !== '') {
            $params['providers'] = strtolower((string) $params['service']);
        }

        // Type filter
        $type = strtolower((string) ($params['type'] ?? 'all'));
        if (!in_array($type, ['all', 'movie', 'tv'], true)) {
            $type = 'all';
        }

        $wantMovies = $type !== 'tv';
        $wantTv = $type !== 'movie';

        $region = $params['region'] ?? 'US';
        $limit = min(max((int) ($params['limit'] ?? 40), 1), 100);
        $page = max((int) ($params['page'] ?? 1), 1);
        $offset = ($page - 1) * $limit;

        $conn = $this->em->getConnection();
        $schema = $conn->createSchemaManager();

        $hasMovies = $schema->tablesExist(['movies']);
        $hasTv = $schema->tablesExist(['tv_shows']);
        $providersSet = !empty($params['providers']) || (!isset($params['providers']) && !empty($params['service']));

        // If no local tables, or provider filter is set -> use TMDB discover
        if ((!$hasMovies && !$hasTv) || $providersSet) {
            return $this->discoverFeed($req, $res, $order);
        }

        // Helpers to adapt to whatever columns exist
        $lowerCols = function (string $table) use ($schema): array {
            try {return array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns($table));} catch (\Throwable $e) {return [];}
        };
        $pick = function (array $cols, array $candidates): ?string {
            foreach ($candidates as $c) {
                if (in_array(strtolower($c), $cols, true)) {
                    return $c;
                }
            }

            return null;
        };

        $parts = [];

        // ----- Movies -----
        if ($wantMovies && $hasMovies) {
            $mc = $lowerCols('movies');

            $mId = $pick($mc, ['tmdb_id', 'id']) ?? 'id';
            $mTitle = $pick($mc, ['title', 'name']) ?? 'title';
            $mPoster = $pick($mc, ['poster_path', 'poster', 'poster_url', 'posterurl']);
            $mPop = $pick($mc, ['popularity', 'vote_average', 'vote_count']);
            $mRelCol = $pick($mc, ['release_date', 'released', 'release', 'release_year', 'year']);

            $exprPoster = $mPoster ? "m.`$mPoster`" : "NULL";
            $exprPop = $mPop ? "m.`$mPop`" : "1";
            $exprRel = match ($mRelCol) {
                'release_date', 'released', 'release' => "m.`$mRelCol`",
                'release_year', 'year' => "STR_TO_DATE(CONCAT(m.`$mRelCol`,'-01-01'), '%Y-%m-%d')",
                default => "NULL",
            };

            $orderSql = match ($order) {
                'release' => 'release_date DESC, popularity DESC',
                'random' => 'RAND()',
                default => 'popularity DESC',
            };

            $parts[] = "
            SELECT m.`id`       AS id,
                    m.`$mId`     AS tmdb_id,
                    0            AS is_tv,
                    m.`$mTitle`  AS title,
                    $exprPop     AS popularity,
                    $exprRel     AS release_date,
                    $exprPoster  AS poster_path
            FROM movies m
            ORDER BY $orderSql
            ";
        }

        // ----- TV -----
        if ($wantTv && $hasTv) {
            $tc = $lowerCols('tv_shows');

            $tId = $pick($tc, ['tmdb_id', 'id']) ?? 'id';
            $tTitle = $pick($tc, ['name', 'title']) ?? 'name';
            $tPoster = $pick($tc, ['poster_path', 'poster', 'poster_url', 'posterurl']);
            $tPop = $pick($tc, ['popularity', 'vote_average', 'vote_count']);
            $tRelCol = $pick($tc, ['first_air_date', 'air_date', 'release_date', 'year']);

            $exprPosterT = $tPoster ? "t.`$tPoster`" : "NULL";
            $exprPopT = $tPop ? "t.`$tPop`" : "1";
            $exprRelT = match ($tRelCol) {
                'first_air_date', 'air_date', 'release_date' => "t.`$tRelCol`",
                'year' => "STR_TO_DATE(CONCAT(t.`$tRelCol`,'-01-01'), '%Y-%m-%d')",
                default => "NULL",
            };

            $orderSqlT = match ($order) {
                'release' => 'release_date DESC, popularity DESC',
                'random' => 'RAND()',
                default => 'popularity DESC',
            };

            $parts[] = "
            SELECT t.`id`        AS id,
                    t.`$tId`      AS tmdb_id,
                    1             AS is_tv,
                    t.`$tTitle`   AS title,
                    $exprPopT     AS popularity,
                    $exprRelT     AS release_date,
                    $exprPosterT  AS poster_path
            FROM tv_shows t
            ORDER BY $orderSqlT
            ";
        }

        // If nothing to query locally, use TMDB
        if (!$parts) {
            return $this->discoverFeed($req, $res, $order);
        }

        $orderExpr = match ($order) {
            'release' => 'release_date DESC, popularity DESC',
            'random' => 'RAND()',
            default => 'popularity DESC',
        };

        $sql = (count($parts) === 1)
        ? sprintf('%s LIMIT %d OFFSET %d', $parts[0], $limit, $offset)
        : sprintf('%s ORDER BY %s LIMIT %d OFFSET %d', implode(' UNION ALL ', $parts), $orderExpr, $limit, $offset);

        $rows = $conn->fetchAllAssociative($sql, []);
        if (!$rows) {
            // last-resort so UI isn’t empty
            return $this->discoverFeed($req, $res, $order);
        }

        return $this->json($res, $rows);
    }

/** GET /search?q=term&limit=40&page=1
 *  Tries DB (if tables exist) and falls back to TMDB search/multi.
 */
    public function search(Request $req, Response $res): Response
    {
        $p = $req->getQueryParams();
        $q = trim((string) ($p['q'] ?? ''));
        $limit = min(max((int) ($p['limit'] ?? 40), 1), 100);
        $page = max((int) ($p['page'] ?? 1), 1);
        $offset = ($page - 1) * $limit;
        $region = (string) ($p['region'] ?? 'US');
        $typeRaw = strtolower((string) ($p['type'] ?? 'all'));
        $type = in_array($typeRaw, ['all', 'movie', 'tv'], true) ? $typeRaw : 'all';
        $wantM = $type !== 'tv';
        $wantT = $type !== 'movie';

        if ($q === '') {
            return $this->json($res, []); // empty query -> empty list
        }

        $conn = $this->em->getConnection();
        $schema = $conn->createSchemaManager();
        $hasM = $schema->tablesExist(['movies']);
        $hasT = $schema->tablesExist(['tv_shows']);

        // ---------- DB path (uses FeedFilters::byProviders) ----------
        $rows = [];
        try {
            if ($hasM && $wantM) {
                $qm = $conn->createQueryBuilder()
                    ->select("
                    m.`id` AS id, m.`" . (function () use ($schema) {$c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('movies'));return in_array('tmdb_id', $c, true) ? 'tmdb_id' : 'id';})() . "` AS tmdb_id,
                    0 AS is_tv,
                    m.`" . (function () use ($schema) {$c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('movies'));return in_array('title', $c, true) ? 'title' : 'name';})() . "` AS title,
                    " . (function () use ($schema) {
                        $c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('movies'));
                        foreach (['popularity', 'vote_average', 'vote_count'] as $k) {
                            if (in_array($k, $c, true)) {
                                return "m.`$k`";
                            }
                        }

                        return "1";
                    })() . " AS popularity,
                    NULL AS release_date,
                    " . (function () use ($schema) {
                        $c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('movies'));
                        foreach (['poster_path', 'poster', 'poster_url', 'posterurl'] as $k) {
                            if (in_array($k, $c, true)) {
                                return "m.`$k`";
                            }
                        }

                        return "NULL";
                    })() . " AS poster_path
                ")
                    ->from('movies', 'm')
                    ->where('LOWER(m.`' . (function () use ($schema) {$c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('movies'));return in_array('title', $c, true) ? 'title' : 'name';})() . '`) LIKE :q')
                    ->setParameter('q', '%' . mb_strtolower($q) . '%')
                    ->setFirstResult($offset)
                    ->setMaxResults($limit)
                    ->add('orderBy', 'popularity DESC');

                // provider filter (reuses your helper)
                FeedFilters::byProviders($qm, $p, 'm', false, $region);

                $rowsM = $conn->fetchAllAssociative($qm->getSQL(), $qm->getParameters());
                $rows = array_merge($rows, $rowsM);
            }

            if ($hasT && $wantT) {
                $qt = $conn->createQueryBuilder()
                    ->select("
                    t.`id` AS id, t.`" . (function () use ($schema) {$c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('tv_shows'));return in_array('tmdb_id', $c, true) ? 'tmdb_id' : 'id';})() . "` AS tmdb_id,
                    1 AS is_tv,
                    t.`" . (function () use ($schema) {$c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('tv_shows'));return in_array('name', $c, true) ? 'name' : 'title';})() . "` AS title,
                    " . (function () use ($schema) {
                        $c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('tv_shows'));
                        foreach (['popularity', 'vote_average', 'vote_count'] as $k) {
                            if (in_array($k, $c, true)) {
                                return "t.`$k`";
                            }
                        }

                        return "1";
                    })() . " AS popularity,
                    NULL AS release_date,
                    " . (function () use ($schema) {
                        $c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('tv_shows'));
                        foreach (['poster_path', 'poster', 'poster_url', 'posterurl'] as $k) {
                            if (in_array($k, $c, true)) {
                                return "t.`$k`";
                            }
                        }

                        return "NULL";
                    })() . " AS poster_path
                ")
                    ->from('tv_shows', 't')
                    ->where('LOWER(t.`' . (function () use ($schema) {$c = array_map(fn($c) => strtolower($c->getName()), $schema->listTableColumns('tv_shows'));return in_array('name', $c, true) ? 'name' : 'title';})() . '`) LIKE :q')
                    ->setParameter('q', '%' . mb_strtolower($q) . '%')
                    ->setFirstResult($offset)
                    ->setMaxResults($limit)
                    ->add('orderBy', 'popularity DESC');

                FeedFilters::byProviders($qt, $p, 't', true, $region);

                $rowsT = $conn->fetchAllAssociative($qt->getSQL(), $qt->getParameters());
                $rows = array_merge($rows, $rowsT);
            }

            if ($rows) {
                usort($rows, fn($a, $b) => ($b['popularity'] <=> $a['popularity']));
                if (count($rows) > $limit) {
                    $rows = array_slice($rows, 0, $limit);
                }

                return $this->json($res, $rows);
            }
        } catch (\Throwable $e) {
            // fall through to TMDB if DB path fails
        }

        // ---------- TMDB fallback (search + optional provider cross-check) ----------
        $tokens = array_values(array_filter(array_map('trim', preg_split('/[|,]/', (string) ($p['providers'] ?? '')) ?: [])));
        $provIds = $this->resolveProviderIds($tokens);
        $needProvFilter = !empty($provIds);

        $take = function (string $kind) use ($limit, $page, $region, $q, $provIds, $needProvFilter) {
            $rows = [];
            $data = $this->tmdb->search($kind, $q, ['page' => $page, 'include_adult' => 'true', 'region' => $region]);
            foreach (($data['results'] ?? []) as $r) {
                $id = (int) ($r['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                // provider filter if requested
                if ($needProvFilter) {
                    $plist = $this->tmdb->titleProviders($kind, $id, $region);
                    $ids = array_map(fn($p) => (int) $p['id'], $plist);
                    $ok = count(array_intersect($ids, $provIds)) > 0;
                    if (!$ok) {
                        continue;
                    }

                }

                $rows[] = [
                    'id' => $this->resolveLocalId($kind, $id),
                    'tmdb_id' => $id,
                    'is_tv' => $kind === 'tv' ? 1 : 0,
                    'title' => $kind === 'tv' ? (string) ($r['name'] ?? '') : (string) ($r['title'] ?? ''),
                    'popularity' => (float) ($r['popularity'] ?? 0),
                    'release_date' => $kind === 'tv' ? ($r['first_air_date'] ?? null) : ($r['release_date'] ?? null),
                    'poster_path' => $r['poster_path'] ?? null,
                ];

                if (count($rows) >= $limit) {
                    break;
                }

            }
            return $rows;
        };

        $out = [];
        if ($wantM) {
            $out = array_merge($out, $take('movie'));
        }

        if ($wantT) {
            $out = array_merge($out, $take('tv'));
        }

        usort($out, fn($a, $b) => ($b['popularity'] <=> $a['popularity']));
        if (count($out) > $limit) {
            $out = array_slice($out, 0, $limit);
        }

        return $this->json($res, $out);
    }

    public function titleProviders(Request $req, Response $res, array $args): Response
    {
        $kind = strtolower((string) ($args['kind'] ?? 'movie'));
        $kind = $kind === 'tv' ? 'tv' : 'movie';
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($res, ['error' => 'Bad id'], 400);
        }

        $region = $req->getQueryParams()['region'] ?? 'US';
        $list = $this->tmdb->titleProviders($kind, $id, $region);
        return $this->json($res, $list);
    }

    private function searchFeed(Request $req, Response $res): Response
    {
        $params = $req->getQueryParams();
        $q = trim((string) ($params['q'] ?? ''));
        if ($q === '') {
            return $this->json($res, []); // nothing to search
        }

        $limit = min(max((int) ($params['limit'] ?? 40), 1), 100);
        $pageUi = max((int) ($params['page'] ?? 1), 1);

        // TMDB search endpoints (no provider filtering here yet)
        $m = $this->tmdb->search('movie', $q, ['page' => $pageUi]);
        $t = $this->tmdb->search('tv', $q, ['page' => $pageUi]);

        $rows = [];

        foreach (($m['results'] ?? []) as $r) {
            $tmdbId = (int) ($r['id'] ?? 0);

            $rows[] = [
                'id' => $this->resolveLocalId('movie', $tmdbId),
                'tmdb_id' => $tmdbId,
                'is_tv' => 0,
                'title' => (string) ($r['title'] ?? ''),
                'popularity' => (float) ($r['popularity'] ?? 0),
                'release_date' => $r['release_date'] ?? null,
                'poster_path' => $r['poster_path'] ?? null,
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }

        if (count($rows) < $limit) {
            foreach (($t['results'] ?? []) as $r) {
                $tmdbId = (int) ($r['id'] ?? 0);

                $rows[] = [
                    'id' => $this->resolveLocalId('tv', $tmdbId),
                    'tmdb_id' => $tmdbId,
                    'is_tv' => 1,
                    'title' => (string) ($r['name'] ?? ''),
                    'popularity' => (float) ($r['popularity'] ?? 0),
                    'release_date' => $r['first_air_date'] ?? null,
                    'poster_path' => $r['poster_path'] ?? null,
                ];
                if (count($rows) >= $limit) {
                    break;
                }
            }
        }

        // Order by popularity desc to keep things consistent
        usort($rows, fn($a, $b) => ($b['popularity'] <=> $a['popularity']));

        return $this->json($res, array_slice($rows, 0, $limit));
    }

    private function discoverFeed(Request $req, Response $res, string $order = 'popularity'): Response
    {
        $params = $req->getQueryParams();
        $region = $params['region'] ?? 'US';
        $limit = min(max((int) ($params['limit'] ?? 40), 1), 100);
        $pageUi = max((int) ($params['page'] ?? 1), 1);

        $type = strtolower((string) ($params['type'] ?? 'all'));

        if (!in_array($type, ['all', 'movie', 'tv'], true)) {
            $type = 'all';
        }

        $wantMovies = $type !== 'tv';
        $wantTv = $type !== 'movie';

        // Map providers=netflix|disney to exact provider IDs via resolver
        $provIds = [];
        if (!empty($params['providers'])) {
            $tokens = array_values(array_filter(array_map('trim', preg_split('/[|,]/', (string) $params['providers']) ?: [])));
            $provIds = $this->resolveProviderIds($tokens);
        }

        // Page TMDB discover and collect rows
        $collect = function (string $type) use ($provIds, $region, $pageUi, $limit) {
            $rows = [];
            $provStr = $provIds ? implode('|', $provIds) : null;
            $page = $pageUi;

            while (count($rows) < $limit) {
                $qs = [
                    'watch_region' => $region,
                    'with_watch_monetization_types' => 'flatrate|ads|free', // optional but useful
                    'sort_by' => 'popularity.desc',
                    'page' => $page,
                    'include_adult' => 'true',
                ];
                if ($provStr) {
                    $qs['with_watch_providers'] = $provStr;
                }

                $data = $this->tmdb->discover($type, $qs);

                foreach ($data['results'] ?? [] as $r) {
                    $tmdbId = (int) ($r['id'] ?? 0);

                    $rows[] = [
                        'id' => $this->resolveLocalId($type, $tmdbId),
                        'tmdb_id' => $tmdbId,
                        'is_tv' => $type === 'tv' ? 1 : 0,
                        'title' => $type === 'tv' ? ($r['name'] ?? '') : ($r['title'] ?? ''),
                        'popularity' => (float) ($r['popularity'] ?? 0),
                        'release_date' => $type === 'tv' ? ($r['first_air_date'] ?? null) : ($r['release_date'] ?? null),
                        'poster_path' => $r['poster_path'] ?? null,
                    ];
                    if (count($rows) >= $limit) {
                        break;
                    }

                }

                $total = (int) ($data['total_pages'] ?? 1);
                if ($page >= $total) {
                    break;
                }

                $page++;
            }
            return $rows;
        };

        // Mix movie + TV (roughly 60/40)
        $rows = [];

        if ($type === 'movie') {
            $rows = $collect('movie');
        } elseif ($type === 'tv') {
            $rows = $collect('tv');
        } else {
            // all: mix ~60/40
            $takeMovies = (int) ceil($limit * 0.6);
            $m = $collect('movie');
            $t = $collect('tv');
            $rows = array_slice($m, 0, $takeMovies);
            $rows = array_merge($rows, array_slice($t, 0, $limit - count($rows)));
        }

        return $this->json($res, $rows);
    }
}