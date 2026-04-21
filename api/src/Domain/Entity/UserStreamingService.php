<?php
declare (strict_types = 1);

namespace PicaFlic\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Pivot linking users to their streaming services. */
#[ORM\Entity]
#[ORM\Table(name: 'user_streaming_services')]
class UserStreamingService
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: StreamingService::class)]
    #[ORM\JoinColumn(name: 'service_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private StreamingService $service;

    public function __construct(User $user, StreamingService $service)
    {
        $this->user = $user;
        $this->service = $service;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getService(): StreamingService
    {
        return $this->service;
    }

    public function getProviderId(): ?int
    {
        return $this->service->getProviderId();
    }
}