<?php
declare(strict_types=1);

namespace PicaFlic\Domain\Repository;

/**
 * Contract for movie feeds used by FeedController.
 */
interface MovieRepository
{
    /**
     * Deck of unswiped, available movies.
     * Optional cursor via $afterId for pagination.
     *
     * @return array<int, array<string,mixed>>
     */
    public function deck(
        int $userId,
        string $region,
        ?string $serviceCode,
        int $limit = 20,
        ?int $afterId = null
    ): array;

    /**
     * For You feed (placeholder: newest-first; later: personalize).
     *
     * @return array<int, array<string,mixed>>
     */
    public function forYou(
        int $userId,
        string $region,
        ?string $serviceCode,
        int $limit = 20,
        ?int $afterId = null
    ): array;

    /**
     * Challenge-me pick: one deterministic “random” suggestion.
     *
     * @return array<string,mixed>|null
     */
    public function challengePick(
        int $userId,
        string $region,
        ?string $serviceCode
    ): ?array;
}
