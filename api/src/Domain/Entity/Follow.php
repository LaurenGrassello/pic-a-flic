<?php
declare(strict_types=1);

namespace PicaFlic\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: "follow")]
class Follow
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "follower_id", nullable: false)]
    private User $follower;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "followee_id", nullable: false)]
    private User $followee;

    #[ORM\Column(name: "created_at", type: "datetime")]
    private \DateTimeInterface $createdAt;

    public function __construct(User $follower, User $followee)
    {
        $this->follower = $follower;
        $this->followee = $followee;
        $this->createdAt = new \DateTimeImmutable();
    }
}