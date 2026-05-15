<?php
declare (strict_types = 1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PicaFlic\Infrastructure\Tmdb\TmdbClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Admin-only tools (protected by X-Admin-Key).
 * - ingestTmdb: upsert trending movies (title, poster, overview, year)
 */
final class AdminController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TmdbClient $tmdb
    ) {}

    public function ingestTmdb(Request $req, Response $res): Response
    {
        $q = $req->getQueryParams();

        $page = max(1, (int) ($q['page'] ?? 1));
        $source = (string) ($q['source'] ?? 'trending');
        $providerId = null;
        $window = in_array(($q['window'] ?? 'week'), ['day', 'week'], true)
        ? (string) ($q['window'] ?? 'week')
        : 'week';

        switch ($source) {
            case 'provider':
                $providerId = max(1, (int) ($q['provider_id'] ?? 0));
                if ($providerId <= 0) {
                    $res->getBody()->write(json_encode([
                        'error' => 'provider_id is required when source=provider',
                    ]));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(422);
                }

                $payload = $this->tmdb->discoverMoviesByProvider($providerId, $page);
                break;
            case 'popular':
                $payload = $this->tmdb->popularMovies($page);
                break;
            case 'top_rated':
                $payload = $this->tmdb->topRatedMovies($page);
                break;
            case 'upcoming':
                $payload = $this->tmdb->upcomingMovies($page);
                break;
            case 'now_playing':
                $payload = $this->tmdb->nowPlayingMovies($page);
                break;
            case 'trending':
            default:
                $payload = $this->tmdb->trendingMovies($window, $page);
                break;
        }

        $results = $payload['results'] ?? [];

        $conn = $this->em->getConnection();
        $stmt = $conn->prepare(<<<SQL
        INSERT INTO movies (tmdb_id, title, release_year, runtime_minutes, poster_path, overview, genre_ids)
        VALUES (:tmdb_id, :title, :release_year, :runtime, :poster, :overview, :genre_ids)
        ON DUPLICATE KEY UPDATE
        title=VALUES(title),
        release_year=VALUES(release_year),
        runtime_minutes=VALUES(runtime_minutes),
        poster_path=VALUES(poster_path),
        overview=VALUES(overview),
        genre_ids=VALUES(genre_ids)
        SQL);

        $providerStmt = ($source === 'provider' && $providerId)
        ? $conn->prepare(<<<SQL
                    INSERT INTO title_providers (tmdb_id, provider_id, region, is_tv)
                    VALUES (:tmdb_id, :provider_id, 'US', 0)
                    ON DUPLICATE KEY UPDATE provider_id=VALUES(provider_id)
                SQL)
        :null;

        $count = 0;

        foreach ($results as $r) {
            $tmdbId = (int) ($r['id'] ?? 0);
            if (!$tmdbId) {
                continue;
            }

            $title = (string) ($r['title'] ?? $r['name'] ?? '');
            $poster = $r['poster_path'] ?? null;
            $overview = $r['overview'] ?? null;
            $year = !empty($r['release_date']) ? (int) substr((string) $r['release_date'], 0, 4) : null;
            $runtime = null;
            $genreIds = isset($r['genre_ids']) && is_array($r['genre_ids'])
            ? implode(',', $r['genre_ids'])
            : null;

            $stmt->bindValue('genre_ids', $genreIds);

            $stmt->bindValue('tmdb_id', $tmdbId);
            $stmt->bindValue('title', $title);
            $stmt->bindValue('release_year', $year, $year === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue('runtime', $runtime, \PDO::PARAM_NULL);
            $stmt->bindValue('poster', $poster);
            $stmt->bindValue('overview', $overview);

            $stmt->executeStatement();

            if ($providerStmt) {
                $providerStmt->bindValue('tmdb_id', $tmdbId);
                $providerStmt->bindValue('provider_id', $providerId);
                $providerStmt->executeStatement();
            }

            $count++;
        }

        $res->getBody()->write(json_encode([
            'ok' => true,
            'source' => $source,
            'window' => $source === 'trending' ? $window : null,
            'provider_id' => $source === 'provider' ? $providerId : null,
            'page' => $page,
            'ingested' => $count,
        ]));

        return $res->withHeader('Content-Type', 'application/json');
    }
}