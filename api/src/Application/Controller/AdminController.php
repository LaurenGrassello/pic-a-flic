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
INSERT INTO movies (tmdb_id, title, release_year, runtime_minutes, poster_path, overview)
VALUES (:tmdb_id, :title, :release_year, :runtime, :poster, :overview)
ON DUPLICATE KEY UPDATE
  title=VALUES(title),
  release_year=VALUES(release_year),
  runtime_minutes=VALUES(runtime_minutes),
  poster_path=VALUES(poster_path),
  overview=VALUES(overview)
SQL);

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

            $stmt->bindValue('tmdb_id', $tmdbId);
            $stmt->bindValue('title', $title);
            $stmt->bindValue('release_year', $year, $year === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue('runtime', $runtime, \PDO::PARAM_NULL);
            $stmt->bindValue('poster', $poster);
            $stmt->bindValue('overview', $overview);

            $stmt->executeStatement();
            $count++;
        }

        $res->getBody()->write(json_encode([
            'ok' => true,
            'source' => $source,
            'window' => $source === 'trending' ? $window : null,
            'provider_id' => $source === 'provider' ? 'providerId' : null,
            'page' => $page,
            'ingested' => $count,
        ]));

        return $res->withHeader('Content-Type', 'application/json');
    }
}