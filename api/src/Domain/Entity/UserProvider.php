<?php
declare(strict_types=1);

namespace PicaFlic\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: "user_providers",
    uniqueConstraints: [new ORM\UniqueConstraint(name: "uq_user_provider", columns: ["user_id", "provider_id"])]
)]
class UserProvider
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Provider::class)]
    #[ORM\JoinColumn(name: "provider_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private Provider $provider;

    public function __construct(User $user, Provider $provider)
    {
        $this->user     = $user;
        $this->provider = $provider;
    }
}