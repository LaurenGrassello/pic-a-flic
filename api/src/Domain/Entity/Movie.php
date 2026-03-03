<?php
declare(strict_types=1);

namespace PicaFlic\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use PicaFlic\Domain\Repository\MovieRepository;

/**
 * Movie entity stores a subset of TMDb fields for demo.
 */
#[ORM\Entity(repositoryClass: MovieRepository::class)]
#[ORM\Table(name: "movies")]
class Movie
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: "integer")]
    private int $id;

    // map to tmdb_id
    #[ORM\Column(name: "tmdb_id", type: "integer", unique: true)]
    private int $tmdbId;

    #[ORM\Column(type: "string", length: 255)]
    private string $title;

    // map to release_year
    #[ORM\Column(name: "release_year", type: "integer", nullable: true)]
    private ?int $releaseYear = null;

    // map to runtime_minutes
    #[ORM\Column(name: "runtime_minutes", type: "integer", nullable: true)]
    private ?int $runtimeMinutes = null;

    public function __construct(int $tmdbId, string $title)
    {
        $this->tmdbId = $tmdbId;
        $this->title  = $title;
    }

    public function getId(): int { 
      return $this->id; 
    }

    public function getTmdbId(): int { 
      return $this->tmdbId; 
    }

    public function getTitle(): string { 
      return $this->title;
     }
}