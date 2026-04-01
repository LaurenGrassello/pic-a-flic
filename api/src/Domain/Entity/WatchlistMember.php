<?php
declare (strict_types = 1);

namespace PicaFlic\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'watchlist_members')]
class WatchlistMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Watchlist::class)]
    #[ORM\JoinColumn(name: 'watchlist_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Watchlist $watchlist;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'joined_at', type: 'datetime_immutable')]
    private DateTimeImmutable $joinedAt;

    public function __construct(Watchlist $watchlist, User $user)
    {
        $this->watchlist = $watchlist;
        $this->user = $user;
        $this->joinedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWatchlist(): Watchlist
    {
        return $this->watchlist;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getJoinedAt(): DateTimeImmutable
    {
        return $this->joinedAt;
    }
}