<?php
declare(strict_types=1);

namespace PicaFlic\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "password_resets")]
class PasswordReset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(name: "token_hash", type: "string", length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(name: "expires_at", type: "datetime")]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(name: "used_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $usedAt = null;

    #[ORM\Column(name: "created_at", type: "datetime")]
    private \DateTimeInterface $createdAt;

    public function __construct(User $user, string $tokenHash, \DateTimeInterface $expiresAt)
    {
        $this->user      = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new DateTimeImmutable();
    }
}