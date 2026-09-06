<?php
declare (strict_types = 1);

/**
 * Daily catalog sync.
 *
 * For each streaming service in `streaming_services`, pulls the top ~100
 * most popular movies currently available on that service (US region) from
 * TMDB, and upserts them into `movies` + `title_providers`.
 *
 * Run manually:
 *   php bin/sync-catalog.php
 *
 * Intended to be triggered daily via a Railway cron-scheduled service.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\ORM\EntityManagerInterface;
use PicaFlic\Bootstrap\AppBuilder;
use PicaFlic\Infrastructure\Tmdb\TmdbClient;

$basePath = dirname(__DIR__);
$container = AppBuilder::buildContainer($basePath);

/** @var EntityManagerInterface $em */
$em = $container->get(EntityManagerInterface::class);
/** @var TmdbClient $tmdb */
$tmdb = $container->get(TmdbClient::class);

$conn = $em->getConnection();

// ---- Config ----
const TARGET_TITLES_PER_PROVIDER = 1000;
const RESULTS_PER_PAGE = 20; // TMDB's fixed page size
const REGION = 'US';
const REQUEST_DELAY_MICROSECONDS = 300_000; // ~3.3 req/sec, safely under TMDB limits

$pagesNeeded = (int) ceil(TARGET_TITLES_PER_PROVIDER / RESULTS_PER_PAGE);

// ---- Load target providers ----
$providers = $conn->fetchAllAssociative(
    "SELECT provider_id, name FROM streaming_services WHERE provider_id IS NOT NULL"
);

if (!$providers) {
    fwrite(STDERR, "No providers found in streaming_services. Nothing to sync.\n");
    exit(1);
}

$totalUpserted = 0;
$totalLinked = 0;
$startedAt = microtime(true);

foreach ($providers as $provider) {
    $providerId = (int) $provider['provider_id'];
    $providerName = $provider['name'];

    echo "=== Syncing {$providerName} (provider_id={$providerId}) ===\n";

    $seenThisProvider = 0;

    for ($page = 1; $page <= $pagesNeeded; $page++) {
        try {
            $data = $tmdb->discover('movie', [
                'watch_region' => REGION,
                'with_watch_providers' => (string) $providerId,
                'sort_by' => 'popularity.desc',
                'include_adult' => 'false',
                'page' => $page,
            ]);
        } catch (\Throwable $e) {
            fwrite(STDERR, "  TMDB request failed for {$providerName} page {$page}: " . $e->getMessage() . "\n");
            continue;
        }

        $results = $data['results'] ?? [];
        if (!$results) {
            break; // no more pages available
        }

        foreach ($results as $movie) {
            $tmdbId = (int) ($movie['id'] ?? 0);
            if ($tmdbId <= 0) {
                continue;
            }

            $title = (string) ($movie['title'] ?? '');
            $posterPath = $movie['poster_path'] ?? null;
            $overview = $movie['overview'] ?? null;
            $popularity = (int) round((float) ($movie['popularity'] ?? 0));

            $genreIds = $movie['genre_ids'] ?? [];
            $genreIdsStr = is_array($genreIds) ? implode(',', array_map('intval', $genreIds)) : null;

            $releaseDate = $movie['release_date'] ?? null;
            $releaseYear = ($releaseDate && strlen($releaseDate) >= 4)
            ? (int) substr($releaseDate, 0, 4)
            : null;

            // Upsert movie
            $conn->executeStatement(
                "
                INSERT INTO movies (tmdb_id, title, release_year, poster_path, genre_ids, overview, popularity)
                VALUES (:tmdb_id, :title, :release_year, :poster_path, :genre_ids, :overview, :popularity)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    release_year = VALUES(release_year),
                    poster_path = VALUES(poster_path),
                    genre_ids = VALUES(genre_ids),
                    overview = VALUES(overview),
                    popularity = VALUES(popularity)
                ",
                [
                    'tmdb_id' => $tmdbId,
                    'title' => $title,
                    'release_year' => $releaseYear,
                    'poster_path' => $posterPath,
                    'genre_ids' => $genreIdsStr,
                    'overview' => $overview,
                    'popularity' => $popularity,
                ]
            );
            $totalUpserted++;

            // Link to this provider (ignore if already linked)
            $conn->executeStatement(
                "
                INSERT IGNORE INTO title_providers (tmdb_id, is_tv, provider_id, region)
                VALUES (:tmdb_id, 0, :provider_id, :region)
                ",
                [
                    'tmdb_id' => $tmdbId,
                    'provider_id' => $providerId,
                    'region' => REGION,
                ]
            );
            $totalLinked++;

            $seenThisProvider++;
        }

        usleep(REQUEST_DELAY_MICROSECONDS);

        $totalPages = (int) ($data['total_pages'] ?? 1);
        if ($page >= $totalPages) {
            break;
        }
    }

    echo "  -> {$seenThisProvider} titles processed\n";
}

$elapsed = round(microtime(true) - $startedAt, 1);
echo "\nDone. {$totalUpserted} movie upserts, {$totalLinked} provider links, in {$elapsed}s.\n";