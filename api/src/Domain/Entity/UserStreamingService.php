<?php
declare(strict_types=1);
namespace PicaFlic\Domain\Entity;
use Doctrine\ORM\Mapping as ORM;
/** Pivot linking users to their streaming services. */
#[ORM\Entity, ORM\Table(name:"user_streaming_services")]
class UserStreamingService {
  #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"integer")] private int $id;
  #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable:false)] private User $user;
  #[ORM\ManyToOne(targetEntity: StreamingService::class)] #[ORM\JoinColumn(nullable:false)] private StreamingService $service;
  public function __construct(User $user, StreamingService $service){$this->user=$user;$this->service=$service;}
}
