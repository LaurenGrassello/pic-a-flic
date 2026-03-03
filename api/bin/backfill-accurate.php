<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PicaFlic\Bootstrap\AppBuilder;
use Doctrine\ORM\EntityManager;
use PicaFlic\Infrastructure\Tmdb\TmdbClient;

$container = \PicaFlic\Bootstrap\AppBuilder::buildContainer(dirname(__DIR__));
/** @var EntityManager $em */
$em   = $container->get(EntityManager::class);
/** @var TmdbClient $tmdb */
$tmdb = $container->get(TmdbClient::class);

$region     = $argv[1] ?? 'US';
$type       = $argv[2] ?? 'movie';             // 'movie' or 'tv'
$providerId = (int)($argv[3] ?? 0);            // TMDB provider id (e.g., 8 for Netflix)
$fromYear   = (int)($argv[4] ?? date('Y'));    // e.g., 2025
$toYear     = (int)($argv[5] ?? ($fromYear - 5)); // e.g., 2018 (inclusive)
$maxPages   = (int)($argv[6] ?? 5);            // cap per year for speed
$monetize   = $argv[7] ?? 'flatrate';          // 'flatrate|ads|free|rent|buy' pipe ok

if ($providerId <= 0) {
    fwrite(STDERR, "Usage: php bin/backfill-accurate.php [US] [movie|tv] <providerId> [fromYear] [toYear] [maxPages] [monetization]\n");
    exit(1);
}

$conn = $em->getConnection();
$insertLink = function (int $tmdbId, bool $isTv) use ($conn, $region, $providerId) {
    $conn->executeStatement(
        "INSERT INTO title_providers (tmdb_id, is_tv, provider_id, region)
         VALUES (:tid, :is_tv, :pid, :region)
         ON DUPLICATE KEY UPDATE tmdb_id = tmdb_id",
        [
            'tid'    => $tmdbId,
            'is_tv'  => $isTv ? 1 : 0,
            'pid'    => $providerId,
            'region' => $region,
        ]
    );
};

$provStr = (string)$providerId;
$isTv = $type === 'tv';

for ($year = $fromYear; $year >= $toYear; $year--) {
    $page = 1;
    $pagesDone = 0;

    while (true) {
        $qs = [
            'watch_region'                  => $region,
            'with_watch_providers'          => $provStr,   // **single provider**
            'with_watch_monetization_types' => $monetize,
            'include_adult'                 => 'false',
            'sort_by'                       => 'popularity.desc',
            'page'                          => $page,
        ];
        if ($isTv) $qs['first_air_date_year'] = $year;
        else       $qs['primary_release_year'] = $year;

        $data = $tmdb->discover($type, $qs);
        $results = $data['results'] ?? [];
        foreach ($results as $r) {
            $insertLink((int)$r['id'], $isTv);
        }

        $totalPages = (int)($data['total_pages'] ?? 1);
        $page++;
        $pagesDone++;

        if ($page > $totalPages) break;
        if ($pagesDone >= $maxPages) break; // cap for speed
    }

    fwrite(STDERR, sprintf("[backfill:%s] provider=%d year=%d pages=%d\n", $type, $providerId, $year, $pagesDone));
}

fwrite(STDERR, "[done] provider={$providerId} type={$type}\n");