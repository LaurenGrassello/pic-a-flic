<?php
declare(strict_types=1);

namespace PicaFlic\Infrastructure\Tmdb;

use Doctrine\ORM\EntityManager;

final class ProviderCatalogSync
{
    public function __construct(
        private EntityManager $em,
        private TmdbClient $tmdb,
        private string $region = 'US'
    ) {}

    /**
     * Fetch providers (movie+tv) from TMDB, upsert into DB, then return a curated subset (big 7).
     * @return array<int, array{id:int,name:string,logo_path:?string}>
     */
    public function syncProvidersCatalogIndex(): array
    {
        $conn  = $this->em->getConnection();

        // 1) Fetch and de-dupe by provider_id
        $movie = $this->tmdb->watchProviders('movie', $this->region);
        $tv    = $this->tmdb->watchProviders('tv',   $this->region);

        $byId = [];
        foreach (array_merge($movie ?? [], $tv ?? []) as $p) {
            if (!isset($p['provider_id'])) continue;
            $byId[(int)$p['provider_id']] = $p; // de-dupe by ID
        }

        // 2) Upsert into MySQL/MariaDB
        $inserted = 0;
        foreach ($byId as $p) {
            $conn->executeStatement(
                "INSERT INTO providers (id, name, logo_path, last_seen_at)
                 VALUES (:id, :name, :logo, NOW())
                 ON DUPLICATE KEY UPDATE
                   name = VALUES(name),
                   logo_path = VALUES(logo_path),
                   last_seen_at = NOW()",
                [
                    'id'   => (int)$p['provider_id'],
                    'name' => (string)($p['provider_name'] ?? 'Unknown'),
                    'logo' => $p['logo_path'] ?? null,
                ]
            );
            $inserted++;
        }
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "[sync] upserted providers rows = {$inserted}\n");
        }

        // 3) Return curated big-7 (normalize names to match variants: Disney+/Disney Plus, HBO Max/Max, etc.)
        $normalize = static fn(string $s) => preg_replace('/[^a-z0-9]+/', '', strtolower($s));
        $wanted = array_flip([
            'netflix',
            'amazonprimevideo',
            'hulu',
            'disneyplus',
            'max',          // HBO Max / Max
            'appletvplus',
            'peacock',
        ]);

        $keep = [];
        foreach ($byId as $p) {
            $n = (string)($p['provider_name'] ?? '');
            if (isset($wanted[$normalize($n)])) {
                $keep[] = $p;
            }
        }

        return array_map(static function ($p) {
            return [
                'id'        => (int)$p['provider_id'],
                'name'      => (string)$p['provider_name'],
                'logo_path' => $p['logo_path'] ?? null,
            ];
        }, $keep);
    }

    /**
     * Backfill catalog links for selected providers into title_providers.
     *
     * @param int[]  $providerIds
     * @param bool   $tv
     * @param int    $fromYear     inclusive (default = current year)
     * @param int    $toYear       inclusive (default = 1950)
     * @param string $monetization e.g. 'flatrate|free|ads'
     * @param int    $maxPagesPerYear 0 = no cap
     */
    public function backfill(
        array $providerIds,
        bool $tv,
        int $fromYear = null,
        int $toYear = 1950,
        string $monetization = 'flatrate|free|ads',
        int $maxPagesPerYear = 0
    ): void {
        if (!$providerIds) return;

        $type      = $tv ? 'tv' : 'movie';
        $fromYear ??= (int)date('Y');

        $conn    = $this->em->getConnection();
        $provStr = implode('|', $providerIds); // TMDB OR syntax

        for ($year = $fromYear; $year >= $toYear; $year--) {
            $page = 1;
            do {
                $params = [
                    'with_watch_providers'          => $provStr,
                    'watch_region'                  => $this->region,
                    'with_watch_monetization_types' => $monetization,
                    'sort_by'                       => 'popularity.desc',
                    'page'                          => $page,
                    'include_adult'                 => 'false',
                ];
                if ($tv)  { $params['first_air_date_year']  = $year; }
                else      { $params['primary_release_year'] = $year; }

                $data       = $this->tmdb->discover($type, $params);
                $totalPages = (int)($data['total_pages'] ?? 1);
                if ($maxPagesPerYear > 0) $totalPages = min($totalPages, $maxPagesPerYear);

                if (PHP_SAPI === 'cli') {
                    fwrite(STDERR, sprintf(
                        "[sync] %s %d page %d/%d, batch=%d\n",
                        $type, $year, $page, $totalPages, count($data['results'] ?? [])
                    ));
                }

                foreach (($data['results'] ?? []) as $row) {
                    $tmdbId = (int)$row['id'];
                    foreach ($providerIds as $pid) {
                        $conn->executeStatement(
                            "INSERT INTO title_providers (tmdb_id, is_tv, provider_id, region)
                             VALUES (:tid, :is_tv, :pid, :region)
                             ON DUPLICATE KEY UPDATE tmdb_id = tmdb_id",
                            [
                                'tid'    => $tmdbId,
                                'is_tv'  => $tv ? 1 : 0,
                                'pid'    => (int)$pid,
                                'region' => $this->region,
                            ]
                        );
                    }
                }

                $page++;
            } while ($page <= $totalPages);
        }
    }
}