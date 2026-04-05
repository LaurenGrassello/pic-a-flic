<?php
declare (strict_types = 1);

namespace PicaFlic\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'watchlist_invites')]
class WatchlistInvite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Watchlist::class)]
    #[ORM\JoinColumn(name: 'watchlist_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Watchlist $watchlist;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'invited_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $invitedUser;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'invited_by_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $invitedByUser;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(Watchlist $watchlist, User $invitedUser, User $invitedByUser, string $status = 'pending')
    {
        $this->watchlist = $watchlist;
        $this->invitedUser = $invitedUser;
        $this->invitedByUser = $invitedByUser;
        $this->status = $status;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWatchlist(): Watchlist
    {
        return $this->watchlist;
    }

    public function getInvitedUser(): User
    {
        return $this->invitedUser;
    }

    public function getInvitedByUser(): User
    {
        return $this->invitedByUser;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}