<?php
declare (strict_types = 1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PersonalWatchlistController
{
    public function __construct(private EntityManagerInterface $em)
    {}

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /** GET /personal-watchlists */
    public function index(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $rows = $conn->fetchAllAssociative(
            "SELECT pw.id, pw.name, pw.created_at,
                    COUNT(pwm.id) AS movie_count
             FROM personal_watchlists pw
             LEFT JOIN personal_watchlist_movies pwm ON pwm.watchlist_id = pw.id
             WHERE pw.user_id = ?
             GROUP BY pw.id
             ORDER BY pw.created_at DESC",
            [$meId]
        );

        return $this->json($res, ['results' => $rows]);
    }

    /** POST /personal-watchlists  { name } */
    public function create(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return $this->json($res, ['error' => 'Name is required'], 422);
        }

        $conn = $this->em->getConnection();
        $conn->insert('personal_watchlists', [
            'user_id' => $meId,
            'name' => $name,
        ]);

        $id = (int) $conn->lastInsertId();

        return $this->json($res, [
            'ok' => true,
            'watchlist' => ['id' => $id, 'name' => $name, 'movie_count' => 0],
        ], 201);
    }

    /** GET /personal-watchlists/{id}/movies */
    public function movies(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $wlId = (int) ($args['id'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();

        // Verify ownership
        $owner = $conn->fetchOne(
            "SELECT user_id FROM personal_watchlists WHERE id = ?", [$wlId]
        );
        if ((int) $owner !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $rows = $conn->fetchAllAssociative(
            "SELECT m.id, m.tmdb_id, m.title, m.poster_path, m.genre_ids,
                    0 AS is_tv, NULL AS release_date
             FROM personal_watchlist_movies pwm
             JOIN movies m ON m.id = pwm.movie_id
             WHERE pwm.watchlist_id = ?
             ORDER BY pwm.created_at DESC",
            [$wlId]
        );

        return $this->json($res, ['results' => $rows]);
    }

    /** POST /personal-watchlists/{id}/movies  { movie_id } */
    public function addMovie(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $wlId = (int) ($args['id'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();

        // Verify ownership
        $owner = $conn->fetchOne(
            "SELECT user_id FROM personal_watchlists WHERE id = ?", [$wlId]
        );
        if ((int) $owner !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $movieId = (int) ($data['movie_id'] ?? 0);
        if ($movieId <= 0) {
            return $this->json($res, ['error' => 'movie_id required'], 422);
        }

        try {
            $conn->insert('personal_watchlist_movies', [
                'watchlist_id' => $wlId,
                'movie_id' => $movieId,
            ]);
        } catch (\Throwable $e) {
            // Duplicate — already in watchlist, not an error
        }

        return $this->json($res, ['ok' => true]);
    }

    /** DELETE /personal-watchlists/{id}/movies/{movieId} */
    public function removeMovie(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $wlId = (int) ($args['id'] ?? 0);
        $movieId = (int) ($args['movieId'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();

        $owner = $conn->fetchOne(
            "SELECT user_id FROM personal_watchlists WHERE id = ?", [$wlId]
        );
        if ((int) $owner !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $conn->delete('personal_watchlist_movies', [
            'watchlist_id' => $wlId,
            'movie_id' => $movieId,
        ]);

        return $this->json($res, ['ok' => true]);
    }

    /** DELETE /personal-watchlists/{id} */
    public function delete(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $wlId = (int) ($args['id'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();

        $owner = $conn->fetchOne(
            "SELECT user_id FROM personal_watchlists WHERE id = ?", [$wlId]
        );
        if ((int) $owner !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $conn->delete('personal_watchlists', ['id' => $wlId]);

        return $this->json($res, ['ok' => true]);
    }
}