<?php
declare(strict_types=1);
namespace PicaFlic\Domain\Entity;
use Doctrine\ORM\Mapping as ORM;
/** Availability for a movie on a service in a region. */
#[ORM\Entity, ORM\Table(name:"movie_availability")]
class MovieAvailability {
  #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"integer")] private int $id;
  #[ORM\ManyToOne(targetEntity: Movie::class)] #[ORM\JoinColumn(nullable:false)] private Movie $movie;
  #[ORM\ManyToOne(targetEntity: StreamingService::class)] #[ORM\JoinColumn(nullable:false)] private StreamingService $service;
  #[ORM\Column(type:"string", length:10)] private string $region;
  public function __construct(Movie $movie, StreamingService $service, string $region){$this->movie=$movie;$this->service=$service;$this->region=$region;}
}
