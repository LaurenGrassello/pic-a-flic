<?php
declare (strict_types = 1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManager;
use PicaFlic\Domain\Entity\Follow;
use PicaFlic\Domain\Entity\Friendship;
use PicaFlic\Domain\Entity\Movie;
use PicaFlic\Domain\Entity\Swipe;
use PicaFlic\Domain\Entity\User;
use PicaFlic\Domain\Entity\UserMoviePreference;
use PicaFlic\Domain\Entity\Watchlist;
use PicaFlic\Domain\Entity\WatchlistInvite;
use PicaFlic\Domain\Entity\WatchlistMember;
use PicaFlic\Domain\Entity\WatchlistMovie;
use PicaFlic\Domain\Entity\WatchlistSwipe;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Social features: follow/unfollow, swipe, and matches.
 */
final class SocialController
{
    public function __construct(private EntityManager $em)
    {}

    /** POST /social/follow/{userId} */
    public function follow(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $targetId = (int) ($args['userId'] ?? 0);
        if ($meId <= 0 || $targetId <= 0 || $meId === $targetId) {
            return $this->json($res, ['error' => 'Invalid follow target'], 422);
        }

        $me = $this->em->find(User::class, $meId);
        $target = $this->em->find(User::class, $targetId);
        if (!$me || !$target) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        // prevent duplicates
        $exists = $this->em->getRepository(Follow::class)->findOneBy(['follower' => $me, 'followee' => $target]);
        if (!$exists) {
            $this->em->persist(new Follow($me, $target));
            $this->em->flush();
        }
        return $this->json($res, ['ok' => true]);
    }

    /** DELETE /social/follow/{userId} */
    public function unfollow(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $targetId = (int) ($args['userId'] ?? 0);
        $me = $this->em->find(User::class, $meId);
        $target = $this->em->find(User::class, $targetId);
        if (!$me || !$target) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $row = $this->em->getRepository(Follow::class)->findOneBy(['follower' => $me, 'followee' => $target]);
        if ($row) {$this->em->remove($row);
            $this->em->flush();}
        return $this->json($res, ['ok' => true]);
    }

    private function findFriendshipBetween(User $a, User $b): ?Friendship
    {
        $qb = $this->em->createQueryBuilder();

        return $qb->select('f')
            ->from(Friendship::class, 'f')
            ->where('(f.requester = :a AND f.addressee = :b) OR (f.requester = :b AND f.addressee = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function userPayload(User $user, ?string $friendshipStatus = null): array
    {
        return [
            'id' => $user->getId(),
            'display_name' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'friendship_status' => $friendshipStatus,
        ];
    }

    public function searchUsers(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $q = trim((string) ($req->getQueryParams()['q'] ?? ''));

        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        if ($q === '') {
            return $this->json($res, ['results' => []]);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $qb = $this->em->createQueryBuilder();
        $users = $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.id != :me')
            ->andWhere('LOWER(u.displayName) LIKE :q OR LOWER(u.email) LIKE :q')
            ->setParameter('me', $meId)
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($users as $user) {
            $friendship = $this->findFriendshipBetween($me, $user);
            $status = $friendship?->getStatus() ?? 'none';
            $results[] = $this->userPayload($user, $status);
        }

        return $this->json($res, ['results' => $results]);
    }

    public function friends(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $qb = $this->em->createQueryBuilder();
        $rows = $qb->select('f', 'requester', 'addressee')
            ->from(Friendship::class, 'f')
            ->join('f.requester', 'requester')
            ->join('f.addressee', 'addressee')
            ->where('f.requester = :me OR f.addressee = :me')
            ->setParameter('me', $me)
            ->getQuery()
            ->getResult();

        $friends = [];
        $pendingSent = [];
        $pendingReceived = [];

        foreach ($rows as $friendship) {
            $requester = $friendship->getRequester();
            $addressee = $friendship->getAddressee();
            $other = $requester->getId() === $meId ? $addressee : $requester;

            if ($friendship->getStatus() === 'accepted') {
                $friends[] = $this->userPayload($other, 'accepted');
            } elseif ($friendship->getStatus() === 'pending') {
                if ($requester->getId() === $meId) {
                    $pendingSent[] = $this->userPayload($other, 'pending_sent');
                } else {
                    $pendingReceived[] = $this->userPayload($other, 'pending_received');
                }
            }
        }

        return $this->json($res, [
            'friends' => $friends,
            'pending_sent' => $pendingSent,
            'pending_received' => $pendingReceived,
        ]);
    }

    public function requestFriend(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $targetId = (int) ($args['userId'] ?? 0);

        if ($meId <= 0 || $targetId <= 0 || $meId === $targetId) {
            return $this->json($res, ['error' => 'Invalid friend target'], 422);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        /** @var User|null $target */
        $target = $this->em->find(User::class, $targetId);

        if (!$me || !$target) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $existing = $this->findFriendshipBetween($me, $target);
        if ($existing) {
            return $this->json($res, [
                'ok' => false,
                'error' => 'Friendship already exists',
                'status' => $existing->getStatus(),
            ], 409);
        }

        $friendship = new Friendship($me, $target, 'pending');
        $this->em->persist($friendship);
        $this->em->flush();

        return $this->json($res, ['ok' => true, 'status' => 'pending'], 201);
    }

    public function acceptFriend(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $requesterId = (int) ($args['userId'] ?? 0);

        if ($meId <= 0 || $requesterId <= 0 || $meId === $requesterId) {
            return $this->json($res, ['error' => 'Invalid friend target'], 422);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        /** @var User|null $requester */
        $requester = $this->em->find(User::class, $requesterId);

        if (!$me || !$requester) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $repo = $this->em->getRepository(Friendship::class);
        $friendship = $repo->findOneBy([
            'requester' => $requester,
            'addressee' => $me,
            'status' => 'pending',
        ]);

        if (!$friendship) {
            return $this->json($res, ['error' => 'Pending request not found'], 404);
        }

        $friendship->setStatus('accepted');
        $this->em->flush();

        return $this->json($res, ['ok' => true, 'status' => 'accepted']);
    }

    public function removeFriend(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $targetId = (int) ($args['userId'] ?? 0);

        if ($meId <= 0 || $targetId <= 0 || $meId === $targetId) {
            return $this->json($res, ['error' => 'Invalid friend target'], 422);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        /** @var User|null $target */
        $target = $this->em->find(User::class, $targetId);

        if (!$me || !$target) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $friendship = $this->findFriendshipBetween($me, $target);
        if (!$friendship) {
            return $this->json($res, ['error' => 'Friendship not found'], 404);
        }

        $this->em->remove($friendship);
        $this->em->flush();

        return $this->json($res, ['ok' => true]);
    }

    private function areAcceptedFriends(User $a, User $b): bool
    {
        $friendship = $this->findFriendshipBetween($a, $b);
        return $friendship !== null && $friendship->getStatus() === 'accepted';
    }

    public function createWatchlist(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));
        $memberIds = array_values(array_unique(array_map('intval', (array) ($data['member_ids'] ?? []))));

        if ($name === '') {
            return $this->json($res, ['error' => 'Watchlist name is required'], 422);
        }

        $memberIds = array_values(array_filter($memberIds, fn(int $id) => $id > 0 && $id !== $meId));

        $totalPeople = 1 + count($memberIds);
        if ($totalPeople < 2) {
            return $this->json($res, ['error' => 'A shared watchlist must include at least 1 invited friend'], 422);
        }

        if ($totalPeople > 5) {
            return $this->json($res, ['error' => 'A shared watchlist can include at most 5 total people'], 422);
        }

        $invitedUsers = [];

        foreach ($memberIds as $memberId) {
            /** @var User|null $user */
            $user = $this->em->find(User::class, $memberId);
            if (!$user) {
                return $this->json($res, ['error' => "User {$memberId} not found"], 404);
            }

            if (!$this->areAcceptedFriends($me, $user)) {
                return $this->json($res, ['error' => "User {$memberId} is not an accepted friend"], 422);
            }

            $invitedUsers[$memberId] = $user;
        }

        $watchlist = new Watchlist($me, $name);
        $this->em->persist($watchlist);
        $this->em->flush();

        // creator becomes the only immediate member
        $this->em->persist(new WatchlistMember($watchlist, $me));

        // selected friends become invites
        foreach ($invitedUsers as $invitedUser) {
            $this->em->persist(new WatchlistInvite($watchlist, $invitedUser, $me, 'pending'));
        }

        $this->em->flush();

        return $this->json($res, [
            'ok' => true,
            'watchlist' => [
                'id' => $watchlist->getId(),
                'name' => $watchlist->getName(),
                'created_by' => $watchlist->getCreatedBy()->getId(),
                'member_count' => 1,
                'invite_count' => count($invitedUsers),
            ],
        ], 201);
    }

    public function watchlists(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $qb = $this->em->createQueryBuilder();
        $watchlists = $qb->select('w', 'creator')
            ->from(Watchlist::class, 'w')
            ->join('w.createdBy', 'creator')
            ->join(WatchlistMember::class, 'wm', 'WITH', 'wm.watchlist = w')
            ->where('wm.user = :uid')
            ->setParameter('uid', $meId)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($watchlists as $watchlist) {
            $countQb = $this->em->createQueryBuilder();
            $memberCount = (int) $countQb->select('COUNT(wm2.id)')
                ->from(WatchlistMember::class, 'wm2')
                ->where('wm2.watchlist = :watchlist')
                ->setParameter('watchlist', $watchlist)
                ->getQuery()
                ->getSingleScalarResult();

            $results[] = [
                'id' => $watchlist->getId(),
                'name' => $watchlist->getName(),
                'created_by' => $watchlist->getCreatedBy()->getId(),
                'member_count' => $memberCount,
            ];
        }

        return $this->json($res, ['results' => $results]);
    }

    public function watchlist(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $watchlistId = (int) ($args['watchlistId'] ?? 0);

        if ($meId <= 0 || $watchlistId <= 0) {
            return $this->json($res, ['error' => 'Invalid request'], 422);
        }

        /** @var Watchlist|null $watchlist */
        $watchlist = $this->em->find(Watchlist::class, $watchlistId);
        if (!$watchlist) {
            return $this->json($res, ['error' => 'Watchlist not found'], 404);
        }

        $memberRepo = $this->em->getRepository(WatchlistMember::class);
        $memberships = $memberRepo->findBy(['watchlist' => $watchlist]);

        $isMember = false;
        $members = [];

        foreach ($memberships as $membership) {
            $user = $membership->getUser();
            if ($user->getId() === $meId) {
                $isMember = true;
            }

            $members[] = [
                'id' => $user->getId(),
                'display_name' => $user->getDisplayName(),
                'email' => $user->getEmail(),
            ];
        }

        if (!$isMember) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        return $this->json($res, [
            'id' => $watchlist->getId(),
            'name' => $watchlist->getName(),
            'created_by' => $watchlist->getCreatedBy()->getId(),
            'members' => $members,
        ]);
    }

    public function watchlistSwipe(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $watchlistId = (int) ($args['watchlistId'] ?? 0);

        if ($meId <= 0 || $watchlistId <= 0) {
            return $this->json($res, ['error' => 'Invalid request'], 422);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        /** @var Watchlist|null $watchlist */
        $watchlist = $this->em->find(Watchlist::class, $watchlistId);

        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        if (!$watchlist) {
            return $this->json($res, ['error' => 'Watchlist not found'], 404);
        }

        $memberRepo = $this->em->getRepository(WatchlistMember::class);
        $memberships = $memberRepo->findBy(['watchlist' => $watchlist]);

        $memberUsers = [];
        $isMember = false;

        foreach ($memberships as $membership) {
            $user = $membership->getUser();
            $memberUsers[] = $user;

            if ($user->getId() === $meId) {
                $isMember = true;
            }
        }

        if (!$isMember) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $movieId = (int) ($data['movie_id'] ?? 0);
        $status = trim((string) ($data['status'] ?? ''));

        if (!in_array($status, ['picked', 'passed'], true)) {
            return $this->json($res, ['error' => 'Status must be picked or passed'], 422);
        }

        /** @var Movie|null $movie */
        $movie = $this->em->find(Movie::class, $movieId);
        if (!$movie) {
            return $this->json($res, ['error' => 'Movie not found'], 404);
        }

        $swipeRepo = $this->em->getRepository(WatchlistSwipe::class);
        $existing = $swipeRepo->findOneBy([
            'watchlist' => $watchlist,
            'user' => $me,
            'movie' => $movie,
        ]);

        if ($existing) {
            $existing->setStatus($status);
        } else {
            $this->em->persist(new WatchlistSwipe($watchlist, $me, $movie, $status));
        }

        $this->em->flush();

        if ($status === 'passed') {
            return $this->json($res, [
                'ok' => true,
                'match' => false,
                'matched_users' => [],
                'match_count' => 0,
                'status' => $status,
            ]);
        }

        $memberIds = array_map(fn(User $user) => $user->getId(), $memberUsers);

        $qb = $this->em->createQueryBuilder();
        $matchedSwipes = $qb->select('ws', 'u')
            ->from(WatchlistSwipe::class, 'ws')
            ->join('ws.user', 'u')
            ->where('ws.watchlist = :watchlist')
            ->andWhere('ws.movie = :movie')
            ->andWhere('ws.status = :picked')
            ->andWhere('u.id IN (:memberIds)')
            ->setParameter('watchlist', $watchlist)
            ->setParameter('movie', $movie)
            ->setParameter('picked', 'picked')
            ->setParameter('memberIds', $memberIds)
            ->getQuery()
            ->getResult();

        $matchedUsers = [];
        foreach ($matchedSwipes as $swipe) {
            $user = $swipe->getUser();
            $matchedUsers[] = [
                'id' => $user->getId(),
                'display_name' => $user->getDisplayName(),
                'email' => $user->getEmail(),
            ];
        }

        $matchCount = count($matchedUsers);
        $isMatch = $matchCount >= 2;

        if ($isMatch) {
            $watchlistMovieRepo = $this->em->getRepository(WatchlistMovie::class);
            $existingWatchlistMovie = $watchlistMovieRepo->findOneBy([
                'watchlist' => $watchlist,
                'movie' => $movie,
            ]);

            if (!$existingWatchlistMovie) {
                $this->em->persist(new WatchlistMovie($watchlist, $movie));
                $this->em->flush();
            }
        }

        return $this->json($res, [
            'ok' => true,
            'match' => $isMatch,
            'matched_users' => $matchedUsers,
            'match_count' => $matchCount,
            'status' => $status,
        ]);
    }

    public function watchlistDeck(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $watchlistId = (int) ($args['watchlistId'] ?? 0);

        if ($meId <= 0 || $watchlistId <= 0) {
            return $this->json($res, ['error' => 'Invalid request'], 422);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        /** @var Watchlist|null $watchlist */
        $watchlist = $this->em->find(Watchlist::class, $watchlistId);

        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        if (!$watchlist) {
            return $this->json($res, ['error' => 'Watchlist not found'], 404);
        }

        $memberRepo = $this->em->getRepository(WatchlistMember::class);
        $memberships = $memberRepo->findBy(['watchlist' => $watchlist]);

        $isMember = false;
        foreach ($memberships as $membership) {
            if ($membership->getUser()->getId() === $meId) {
                $isMember = true;
                break;
            }
        }

        if (!$isMember) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $limit = max(1, min(100, (int) ($req->getQueryParams()['limit'] ?? 40)));

        // 1) get personal liked/disliked movie ids to always exclude
        $prefRepo = $this->em->getRepository(UserMoviePreference::class);
        $prefs = $prefRepo->findBy(['user' => $me]);

        $excludedMovieIds = [];
        foreach ($prefs as $pref) {
            if (in_array($pref->getStatus(), ['liked', 'disliked'], true)) {
                $excludedMovieIds[] = $pref->getMovie()->getId();
            }
        }

        // 2) get passed movie ids for this watchlist/user
        $watchlistSwipeRepo = $this->em->getRepository(WatchlistSwipe::class);
        $watchlistSwipes = $watchlistSwipeRepo->findBy([
            'watchlist' => $watchlist,
            'user' => $me,
        ]);

        $passedMovieIds = [];
        $pickedMovieIds = [];
        foreach ($watchlistSwipes as $swipe) {
            if ($swipe->getStatus() === 'passed') {
                $passedMovieIds[] = $swipe->getMovie()->getId();
            } elseif ($swipe->getStatus() === 'picked') {
                $pickedMovieIds[] = $swipe->getMovie()->getId();
            }
        }

        $excludedMovieIds = array_values(array_unique(array_merge($excludedMovieIds, $pickedMovieIds)));

        // 3) unseen first = not excluded and not passed yet
        $qb = $this->em->createQueryBuilder();
        $qb->select('m')
            ->from(Movie::class, 'm')
            ->setMaxResults($limit);

        if (!empty($excludedMovieIds)) {
            $qb->andWhere($qb->expr()->notIn('m.id', ':excludedIds'))
                ->setParameter('excludedIds', $excludedMovieIds);
        }

        if (!empty($passedMovieIds)) {
            $qb->andWhere($qb->expr()->notIn('m.id', ':passedIds'))
                ->setParameter('passedIds', $passedMovieIds);
        }

        $unseenMovies = $qb->getQuery()->getResult();

        $movies = $unseenMovies;

        // 4) if nothing unseen left, recycle passed movies (but still exclude liked/disliked/picked)
        if (count($movies) === 0 && !empty($passedMovieIds)) {
            $qb2 = $this->em->createQueryBuilder();
            $qb2->select('m')
                ->from(Movie::class, 'm')
                ->where($qb2->expr()->in('m.id', ':passedIds'))
                ->setParameter('passedIds', $passedMovieIds)
                ->setMaxResults($limit);

            if (!empty($excludedMovieIds)) {
                $qb2->andWhere($qb2->expr()->notIn('m.id', ':excludedIds'))
                    ->setParameter('excludedIds', $excludedMovieIds);
            }

            $movies = $qb2->getQuery()->getResult();
        }

        $results = [];
        foreach ($movies as $movie) {
            $results[] = [
                'id' => $movie->getId(),
                'tmdb_id' => method_exists($movie, 'getTmdbId') ? $movie->getTmdbId() : null,
                'is_tv' => 0,
                'title' => method_exists($movie, 'getTitle') ? $movie->getTitle() : null,
                'poster_path' => method_exists($movie, 'getPosterPath') ? $movie->getPosterPath() : null,
            ];
        }

        return $this->json($res, ['results' => $results]);
    }

    public function likedMovies(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $repo = $this->em->getRepository(UserMoviePreference::class);
        $prefs = $repo->findBy([
            'user' => $me,
            'status' => 'liked',
        ]);

        $results = [];
        foreach ($prefs as $pref) {
            $movie = $pref->getMovie();
            $results[] = [
                'id' => $movie->getId(),
                'title' => method_exists($movie, 'getTitle') ? $movie->getTitle() : null,
                'tmdb_id' => method_exists($movie, 'getTmdbId') ? $movie->getTmdbId() : null,
                'poster_path' => method_exists($movie, 'getPosterPath') ? $movie->getPosterPath() : null,
            ];
        }

        return $this->json($res, ['results' => $results]);
    }

    public function dislikedMovies(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $repo = $this->em->getRepository(UserMoviePreference::class);
        $prefs = $repo->findBy([
            'user' => $me,
            'status' => 'disliked',
        ]);

        $results = [];
        foreach ($prefs as $pref) {
            $movie = $pref->getMovie();
            $results[] = [
                'id' => $movie->getId(),
                'title' => method_exists($movie, 'getTitle') ? $movie->getTitle() : null,
                'tmdb_id' => method_exists($movie, 'getTmdbId') ? $movie->getTmdbId() : null,
                'poster_path' => method_exists($movie, 'getPosterPath') ? $movie->getPosterPath() : null,
            ];
        }

        return $this->json($res, ['results' => $results]);
    }

    public function watchlistMovies(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $watchlistId = (int) ($args['watchlistId'] ?? 0);

        if ($meId <= 0 || $watchlistId <= 0) {
            return $this->json($res, ['error' => 'Invalid request'], 422);
        }

        /** @var Watchlist|null $watchlist */
        $watchlist = $this->em->find(Watchlist::class, $watchlistId);
        if (!$watchlist) {
            return $this->json($res, ['error' => 'Watchlist not found'], 404);
        }

        $memberRepo = $this->em->getRepository(WatchlistMember::class);
        $memberships = $memberRepo->findBy(['watchlist' => $watchlist]);

        $isMember = false;
        foreach ($memberships as $membership) {
            if ($membership->getUser()->getId() === $meId) {
                $isMember = true;
                break;
            }
        }

        if (!$isMember) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $repo = $this->em->getRepository(WatchlistMovie::class);
        $rows = $repo->findBy(['watchlist' => $watchlist]);

        $results = [];
        foreach ($rows as $row) {
            $movie = $row->getMovie();
            $results[] = [
                'id' => $movie->getId(),
                'title' => method_exists($movie, 'getTitle') ? $movie->getTitle() : null,
                'tmdb_id' => method_exists($movie, 'getTmdbId') ? $movie->getTmdbId() : null,
                'poster_path' => method_exists($movie, 'getPosterPath') ? $movie->getPosterPath() : null,
            ];
        }

        return $this->json($res, ['results' => $results]);
    }

    public function setPreference(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $movieId = (int) ($data['movie_id'] ?? 0);
        $status = trim((string) ($data['status'] ?? ''));

        if (!in_array($status, ['liked', 'disliked'], true)) {
            return $this->json($res, ['error' => 'Status must be liked or disliked'], 422);
        }

        /** @var Movie|null $movie */
        $movie = $this->em->find(Movie::class, $movieId);
        if (!$movie) {
            return $this->json($res, ['error' => 'Movie not found'], 404);
        }

        $repo = $this->em->getRepository(UserMoviePreference::class);
        $existing = $repo->findOneBy([
            'user' => $me,
            'movie' => $movie,
        ]);

        if ($existing) {
            $existing->setStatus($status);
        } else {
            $this->em->persist(new UserMoviePreference($me, $movie, $status));
        }

        $this->em->flush();

        return $this->json($res, [
            'ok' => true,
            'movie_id' => $movieId,
            'status' => $status,
        ]);
    }

    /** POST /social/swipe  body: { "movie_id": 123, "liked": true } */
    public function swipe(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $data = json_decode((string) $req->getBody(), true) ?: [];
        $movieId = (int) ($data['movie_id'] ?? 0);
        $liked = (bool) ($data['liked'] ?? false);

        $me = $this->em->find(User::class, $meId);
        $movie = $this->em->find(Movie::class, $movieId);
        if (!$me || !$movie) {
            return $this->json($res, ['error' => 'Not found'], 404);
        }

        // upsert: one swipe per user+movie
        $repo = $this->em->getRepository(Swipe::class);
        $existing = $repo->findOneBy(['user' => $me, 'movie' => $movie]);
        if ($existing) {
            // reflect new choice
            $refLiked = new \ReflectionProperty(Swipe::class, 'liked');
            $refLiked->setAccessible(true);
            $refLiked->setValue($existing, $liked);
        } else {
            $this->em->persist(new Swipe($me, $movie, $liked));
        }
        $this->em->flush();

        return $this->json($res, ['ok' => true]);
    }

    /**
     * GET /social/matches/{friendId}?limit=20&offset=0
     * Returns movies both users liked.
     */
    public function matches(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $friendId = (int) ($args['friendId'] ?? 0);
        $q = $req->getQueryParams();
        $limit = max(1, min(100, (int) ($q['limit'] ?? 20)));
        $offset = max(0, (int) ($q['offset'] ?? 0));

        $qb = $this->em->createQueryBuilder();
        $qb->select('m.id AS id, m.tmdbId AS tmdbId, m.title AS title')
            ->from(Swipe::class, 's1')
            ->join('s1.movie', 'm')
            ->join(Swipe::class, 's2', 'WITH', 's2.movie = m AND s2.liked = true')
            ->where('s1.user = :me AND s1.liked = true AND s2.user = :friend')
            ->groupBy('m.id')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter('me', $meId)
            ->setParameter('friend', $friendId);

        $rows = $qb->getQuery()->getArrayResult();
        return $this->json($res, ['results' => $rows, 'count' => count($rows)]);
    }

    public function inviteToWatchlist(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $watchlistId = (int) ($args['watchlistId'] ?? 0);

        if ($meId <= 0 || $watchlistId <= 0) {
            return $this->json($res, ['error' => 'Invalid request'], 422);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        /** @var Watchlist|null $watchlist */
        $watchlist = $this->em->find(Watchlist::class, $watchlistId);

        if (!$me || !$watchlist) {
            return $this->json($res, ['error' => 'Not found'], 404);
        }

        $memberRepo = $this->em->getRepository(WatchlistMember::class);
        $memberships = $memberRepo->findBy(['watchlist' => $watchlist]);

        $isMember = false;
        $existingMemberIds = [];

        foreach ($memberships as $membership) {
            $user = $membership->getUser();
            $existingMemberIds[] = $user->getId();

            if ($user->getId() === $meId) {
                $isMember = true;
            }
        }

        if (!$isMember) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $invitedUserId = (int) ($data['user_id'] ?? 0);

        if ($invitedUserId <= 0 || $invitedUserId === $meId) {
            return $this->json($res, ['error' => 'Invalid invited user'], 422);
        }

        /** @var User|null $invitedUser */
        $invitedUser = $this->em->find(User::class, $invitedUserId);
        if (!$invitedUser) {
            return $this->json($res, ['error' => 'Invited user not found'], 404);
        }

        if (in_array($invitedUserId, $existingMemberIds, true)) {
            return $this->json($res, ['error' => 'User is already a member'], 409);
        }

        if (!$this->areAcceptedFriends($me, $invitedUser)) {
            return $this->json($res, ['error' => 'User must be an accepted friend'], 422);
        }

        $inviteRepo = $this->em->getRepository(WatchlistInvite::class);
        $existingInvite = $inviteRepo->findOneBy([
            'watchlist' => $watchlist,
            'invitedUser' => $invitedUser,
        ]);

        if ($existingInvite && $existingInvite->getStatus() === 'pending') {
            return $this->json($res, ['error' => 'Invite already pending'], 409);
        }

        if ($existingInvite) {
            $existingInvite->setStatus('pending');
        } else {
            $this->em->persist(new WatchlistInvite($watchlist, $invitedUser, $me, 'pending'));
        }

        $this->em->flush();

        return $this->json($res, [
            'ok' => true,
            'status' => 'pending',
        ], 201);
    }

    public function watchlistInvites(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        if (!$me) {
            return $this->json($res, ['error' => 'User not found'], 404);
        }

        $repo = $this->em->getRepository(WatchlistInvite::class);
        $invites = $repo->findBy([
            'invitedUser' => $me,
            'status' => 'pending',
        ]);

        $results = [];
        foreach ($invites as $invite) {
            $watchlist = $invite->getWatchlist();
            $invitedBy = $invite->getInvitedByUser();

            $results[] = [
                'id' => $invite->getId(),
                'watchlist_id' => $watchlist->getId(),
                'watchlist_name' => $watchlist->getName(),
                'invited_by_user_id' => $invitedBy->getId(),
                'invited_by_name' => $invitedBy->getDisplayName(),
                'status' => $invite->getStatus(),
            ];
        }

        return $this->json($res, ['results' => $results]);
    }

    public function acceptWatchlistInvite(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $inviteId = (int) ($args['inviteId'] ?? 0);

        if ($meId <= 0 || $inviteId <= 0) {
            return $this->json($res, ['error' => 'Invalid request'], 422);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        /** @var WatchlistInvite|null $invite */
        $invite = $this->em->find(WatchlistInvite::class, $inviteId);

        if (!$me || !$invite) {
            return $this->json($res, ['error' => 'Not found'], 404);
        }

        if ($invite->getInvitedUser()->getId() !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        if ($invite->getStatus() !== 'pending') {
            return $this->json($res, ['error' => 'Invite is no longer pending'], 409);
        }

        $watchlist = $invite->getWatchlist();

        $memberRepo = $this->em->getRepository(WatchlistMember::class);
        $existingMember = $memberRepo->findOneBy([
            'watchlist' => $watchlist,
            'user' => $me,
        ]);

        if (!$existingMember) {
            $this->em->persist(new WatchlistMember($watchlist, $me));
        }

        $invite->setStatus('accepted');
        $this->em->flush();

        return $this->json($res, [
            'ok' => true,
            'status' => 'accepted',
        ]);
    }

    public function declineWatchlistInvite(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $inviteId = (int) ($args['inviteId'] ?? 0);

        if ($meId <= 0 || $inviteId <= 0) {
            return $this->json($res, ['error' => 'Invalid request'], 422);
        }

        /** @var User|null $me */
        $me = $this->em->find(User::class, $meId);
        /** @var WatchlistInvite|null $invite */
        $invite = $this->em->find(WatchlistInvite::class, $inviteId);

        if (!$me || !$invite) {
            return $this->json($res, ['error' => 'Not found'], 404);
        }

        if ($invite->getInvitedUser()->getId() !== $meId) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        if ($invite->getStatus() !== 'pending') {
            return $this->json($res, ['error' => 'Invite is no longer pending'], 409);
        }

        $invite->setStatus('declined');
        $this->em->flush();

        return $this->json($res, [
            'ok' => true,
            'status' => 'declined',
        ]);
    }

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}