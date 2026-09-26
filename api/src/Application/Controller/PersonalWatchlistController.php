<?php
declare (strict_types = 1);

namespace PicaFlic\Application\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PicaFlic\Domain\Entity\Friendship;
use PicaFlic\Domain\Entity\User;
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

    private function areAcceptedFriends(int $aId, int $bId): bool
    {
        /** @var User|null $a */
        $a = $this->em->find(User::class, $aId);
        /** @var User|null $b */
        $b = $this->em->find(User::class, $bId);
        if (!$a || !$b) {
            return false;
        }

        $qb = $this->em->createQueryBuilder();
        $friendship = $qb->select('f')
            ->from(Friendship::class, 'f')
            ->where('(f.requester = :a AND f.addressee = :b) OR (f.requester = :b AND f.addressee = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $friendship !== null && $friendship->getStatus() === 'accepted';
    }

    /**
     * Finds the local `movies` row for a TMDB id, inserting one if it doesn't
     * exist yet. This is what lets a movie/show fresh out of search (which has
     * no local id yet) get added straight to a watchlist.
     *
     * $data is expected to (optionally) contain: title, poster_path, genre_ids,
     * release_date (a "YYYY-MM-DD" string, or null), popularity.
     */
    private function findOrCreateMovie(Connection $conn, int $tmdbId, array $data): int
    {
        $existingId = $conn->fetchOne("SELECT id FROM movies WHERE tmdb_id = ?", [$tmdbId]);
        if ($existingId !== false) {
            return (int) $existingId;
        }

        $title = trim((string) ($data['title'] ?? ''));
        $posterPath = $data['poster_path'] ?? null;
        $genreIds = $data['genre_ids'] ?? null;

        $releaseYear = null;
        if (!empty($data['release_date'])) {
            $releaseYear = (int) substr((string) $data['release_date'], 0, 4);
            if ($releaseYear <= 0) {
                $releaseYear = null;
            }
        }

        $popularity = null;
        if (isset($data['popularity']) && is_numeric($data['popularity'])) {
            $popularity = (int) round((float) $data['popularity']);
        }

        try {
            $conn->insert('movies', [
                'tmdb_id' => $tmdbId,
                'title' => $title !== '' ? $title : 'Untitled',
                'release_year' => $releaseYear,
                'poster_path' => $posterPath,
                'genre_ids' => $genreIds,
                'popularity' => $popularity,
            ]);
            return (int) $conn->lastInsertId();
        } catch (\Throwable $e) {
            // Race: another request inserted this tmdb_id between our SELECT and INSERT.
            $existingId = $conn->fetchOne("SELECT id FROM movies WHERE tmdb_id = ?", [$tmdbId]);
            if ($existingId !== false) {
                return (int) $existingId;
            }
            throw $e;
        }
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

    /**
     * POST /personal-watchlists/{id}/movies
     *   { movie_id }  — existing local movie, OR
     *   { tmdb_id, title?, poster_path?, genre_ids?, release_date?, popularity? }
     *     — a movie/show that may not exist locally yet; we find-or-create it.
     */
    public function addMovie(Request $req, Response $res, array $args): Response
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

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $movieId = (int) ($data['movie_id'] ?? 0);

        if ($movieId <= 0) {
            $tmdbId = (int) ($data['tmdb_id'] ?? 0);
            if ($tmdbId <= 0) {
                return $this->json($res, ['error' => 'movie_id or tmdb_id required'], 422);
            }
            $movieId = $this->findOrCreateMovie($conn, $tmdbId, $data);
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

    // =========================================================
    // Sharing / friend swipe-matching
    // =========================================================

    /** POST /personal-watchlists/{id}/share  { friend_user_id } */
    public function share(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $wlId = (int) ($args['id'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();

        $owner = $conn->fetchOne("SELECT user_id FROM personal_watchlists WHERE id = ?", [$wlId]);
        if ($owner === false) {
            return $this->json($res, ['error' => 'Watchlist not found'], 404);
        }
        if ((int) $owner !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $friendUserId = (int) ($data['friend_user_id'] ?? 0);

        if ($friendUserId <= 0 || $friendUserId === $meId) {
            return $this->json($res, ['error' => 'Invalid friend'], 422);
        }

        if (!$conn->fetchOne("SELECT id FROM users WHERE id = ?", [$friendUserId])) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        if (!$this->areAcceptedFriends($meId, $friendUserId)) {
            return $this->json($res, ['error' => 'User must be an accepted friend'], 422);
        }

        $existing = $conn->fetchAssociative(
            "SELECT id, status FROM personal_watchlist_shares WHERE watchlist_id = ? AND shared_with_user_id = ?",
            [$wlId, $friendUserId]
        );

        if ($existing && $existing['status'] === 'pending') {
            return $this->json($res, ['error' => 'Share already pending'], 409);
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($existing) {
            $conn->update(
                'personal_watchlist_shares',
                ['status' => 'pending', 'updated_at' => $now],
                ['id' => $existing['id']]
            );
            $shareId = (int) $existing['id'];
        } else {
            $conn->insert('personal_watchlist_shares', [
                'watchlist_id' => $wlId,
                'owner_user_id' => $meId,
                'shared_with_user_id' => $friendUserId,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $shareId = (int) $conn->lastInsertId();
        }

        return $this->json($res, ['ok' => true, 'share_id' => $shareId, 'status' => 'pending'], 201);
    }

    /** GET /personal-watchlists/shares/received */
    public function receivedShares(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $rows = $conn->fetchAllAssociative(
            "SELECT pws.id AS share_id, pws.watchlist_id, pw.name AS watchlist_name,
                    pws.owner_user_id, ou.display_name AS owner_display_name,
                    pws.status,
                    (SELECT COUNT(*) FROM personal_watchlist_swipes s
                        WHERE s.share_id = pws.id AND s.status = 'picked') AS match_count
             FROM personal_watchlist_shares pws
             JOIN personal_watchlists pw ON pw.id = pws.watchlist_id
             JOIN users ou ON ou.id = pws.owner_user_id
             WHERE pws.shared_with_user_id = ?
             ORDER BY pws.created_at DESC",
            [$meId]
        );

        return $this->json($res, ['results' => $rows]);
    }

    /** GET /personal-watchlists/shares/{shareId} */
    public function shareInfo(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $shareId = (int) ($args['shareId'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $row = $conn->fetchAssociative(
            "SELECT pws.id AS share_id, pws.watchlist_id, pw.name AS watchlist_name,
                    pws.owner_user_id, ou.display_name AS owner_display_name,
                    pws.shared_with_user_id, pws.status
             FROM personal_watchlist_shares pws
             JOIN personal_watchlists pw ON pw.id = pws.watchlist_id
             JOIN users ou ON ou.id = pws.owner_user_id
             WHERE pws.id = ?",
            [$shareId]
        );

        if (!$row) {
            return $this->json($res, ['error' => 'Share not found'], 404);
        }

        if ($meId !== (int) $row['owner_user_id'] && $meId !== (int) $row['shared_with_user_id']) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        return $this->json($res, $row);
    }

    /** POST /personal-watchlists/shares/{shareId}/accept */
    public function acceptShare(Request $req, Response $res, array $args): Response
    {
        return $this->respondToShare($req, $res, $args, 'accepted');
    }

    /** POST /personal-watchlists/shares/{shareId}/decline */
    public function declineShare(Request $req, Response $res, array $args): Response
    {
        return $this->respondToShare($req, $res, $args, 'declined');
    }

    private function respondToShare(Request $req, Response $res, array $args, string $newStatus): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $shareId = (int) ($args['shareId'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $row = $conn->fetchAssociative(
            "SELECT id, shared_with_user_id, status FROM personal_watchlist_shares WHERE id = ?",
            [$shareId]
        );

        if (!$row) {
            return $this->json($res, ['error' => 'Share not found'], 404);
        }
        if ((int) $row['shared_with_user_id'] !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }
        if ($row['status'] !== 'pending') {
            return $this->json($res, ['error' => 'Share is no longer pending'], 409);
        }

        $conn->update(
            'personal_watchlist_shares',
            ['status' => $newStatus, 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $shareId]
        );

        return $this->json($res, ['ok' => true, 'status' => $newStatus]);
    }

    /** GET /personal-watchlists/shares/{shareId}/deck */
    public function shareDeck(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $shareId = (int) ($args['shareId'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $share = $conn->fetchAssociative(
            "SELECT watchlist_id, shared_with_user_id, status FROM personal_watchlist_shares WHERE id = ?",
            [$shareId]
        );

        if (!$share) {
            return $this->json($res, ['error' => 'Share not found'], 404);
        }
        if ((int) $share['shared_with_user_id'] !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }
        if ($share['status'] !== 'accepted') {
            return $this->json($res, ['error' => 'Share has not been accepted yet'], 409);
        }

        $rows = $conn->fetchAllAssociative(
            "SELECT m.id, m.tmdb_id, m.title, m.poster_path, m.genre_ids,
                    0 AS is_tv, NULL AS release_date
             FROM personal_watchlist_movies pwm
             JOIN movies m ON m.id = pwm.movie_id
             WHERE pwm.watchlist_id = ?
               AND m.id NOT IN (
                   SELECT movie_id FROM personal_watchlist_swipes WHERE share_id = ?
               )
             ORDER BY pwm.created_at DESC",
            [$share['watchlist_id'], $shareId]
        );

        return $this->json($res, ['results' => $rows]);
    }

    /** PATCH /personal-watchlists/{id}  { name } */
    public function rename(Request $req, Response $res, array $args): Response
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

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->json($res, ['error' => 'Name is required'], 422);
        }

        $conn->update('personal_watchlists', ['name' => $name], ['id' => $wlId]);

        return $this->json($res, ['ok' => true, 'watchlist' => ['id' => $wlId, 'name' => $name]]);
    }

    /** POST /personal-watchlists/shares/{shareId}/swipe  { movie_id, status } */
    public function shareSwipe(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $shareId = (int) ($args['shareId'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $share = $conn->fetchAssociative(
            "SELECT watchlist_id, shared_with_user_id, status FROM personal_watchlist_shares WHERE id = ?",
            [$shareId]
        );

        if (!$share) {
            return $this->json($res, ['error' => 'Share not found'], 404);
        }
        if ((int) $share['shared_with_user_id'] !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }
        if ($share['status'] !== 'accepted') {
            return $this->json($res, ['error' => 'Share has not been accepted yet'], 409);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $movieId = (int) ($data['movie_id'] ?? 0);
        $status = trim((string) ($data['status'] ?? ''));

        if (!in_array($status, ['picked', 'passed'], true)) {
            return $this->json($res, ['error' => 'Status must be picked or passed'], 422);
        }

        $belongs = $conn->fetchOne(
            "SELECT 1 FROM personal_watchlist_movies WHERE watchlist_id = ? AND movie_id = ?",
            [$share['watchlist_id'], $movieId]
        );
        if (!$belongs) {
            return $this->json($res, ['error' => 'Movie is not in this watchlist'], 422);
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $existing = $conn->fetchOne(
            "SELECT id FROM personal_watchlist_swipes WHERE share_id = ? AND movie_id = ?",
            [$shareId, $movieId]
        );

        if ($existing) {
            $conn->update(
                'personal_watchlist_swipes',
                ['status' => $status, 'updated_at' => $now],
                ['id' => $existing]
            );
        } else {
            $conn->insert('personal_watchlist_swipes', [
                'share_id' => $shareId,
                'movie_id' => $movieId,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // The owner already "picked" every movie in the list by adding it —
        // so a recipient pick is an instant match, no second swiper needed.
        $isMatch = $status === 'picked';

        return $this->json($res, [
            'ok' => true,
            'match' => $isMatch,
            'status' => $status,
        ]);
    }

    /** GET /personal-watchlists/{id}/shares — owner-only: who this watchlist has been shared with. */
    public function sentShares(Request $req, Response $res, array $args): Response
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

        $rows = $conn->fetchAllAssociative(
            "SELECT pws.id AS share_id, pws.shared_with_user_id,
                    u.display_name AS shared_with_display_name,
                    pws.status,
                    (SELECT COUNT(*) FROM personal_watchlist_swipes s
                        WHERE s.share_id = pws.id AND s.status = 'picked') AS match_count
             FROM personal_watchlist_shares pws
             JOIN users u ON u.id = pws.shared_with_user_id
             WHERE pws.watchlist_id = ?
             ORDER BY pws.created_at DESC",
            [$wlId]
        );

        return $this->json($res, ['results' => $rows]);
    }

    /** GET /personal-watchlists/shares/{shareId}/matches */
    public function shareMatches(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $shareId = (int) ($args['shareId'] ?? 0);
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $share = $conn->fetchAssociative(
            "SELECT owner_user_id, shared_with_user_id FROM personal_watchlist_shares WHERE id = ?",
            [$shareId]
        );

        if (!$share) {
            return $this->json($res, ['error' => 'Share not found'], 404);
        }
        if ($meId !== (int) $share['owner_user_id'] && $meId !== (int) $share['shared_with_user_id']) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $rows = $conn->fetchAllAssociative(
            "SELECT m.id, m.tmdb_id, m.title, m.poster_path, m.genre_ids,
                    0 AS is_tv, NULL AS release_date
             FROM personal_watchlist_swipes pws
             JOIN movies m ON m.id = pws.movie_id
             WHERE pws.share_id = ? AND pws.status = 'picked'
             ORDER BY pws.updated_at DESC",
            [$shareId]
        );

        return $this->json($res, ['results' => $rows]);
    }
}