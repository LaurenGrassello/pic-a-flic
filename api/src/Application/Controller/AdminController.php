<?php
declare(strict_types=1);

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
        $q      = $req->getQueryParams();
        $page   = max(1, (int)($q['page'] ?? 1));
        $window = in_array($q['window'] ?? 'week', ['day','week'], true) ? ($q['window'] ?? 'week') : 'week';

        $payload = $this->tmdb->trendingMovies($window, $page);
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
            $tmdbId = (int)($r['id'] ?? 0);
            if (!$tmdbId) continue;

            $title    = (string)($r['title'] ?? $r['name'] ?? '');
            $poster   = $r['poster_path'] ?? null;
            $overview = $r['overview']    ?? null;
            $year     = !empty($r['release_date']) ? (int)substr($r['release_date'], 0, 4) : null;
            $runtime  = null; 

            $stmt->bindValue('tmdb_id', $tmdbId);
            $stmt->bindValue('title', $title);
            $stmt->bindValue('release_year', $year, $year === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue('runtime', $runtime, \PDO::PARAM_NULL);
            $stmt->bindValue('poster', $poster);
            $stmt->bindValue('overview', $overview);

            $stmt->executeStatement();
            $count++;
        }

        $res->getBody()->write(json_encode(['ok'=>true,'ingested'=>$count,'page'=>$page,'window'=>$window]));
        return $res->withHeader('Content-Type','application/json');
    }
}