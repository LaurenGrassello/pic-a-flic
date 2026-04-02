<?php
declare (strict_types = 1);

namespace PicaFlic\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'watchlist_movies')]
class WatchlistMovie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Watchlist::class)]
    #[ORM\JoinColumn(name: 'watchlist_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Watchlist $watchlist;

    #[ORM\ManyToOne(targetEntity: Movie::class)]
    #[ORM\JoinColumn(name: 'movie_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Movie $movie;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(Watchlist $watchlist, Movie $movie)
    {
        $this->watchlist = $watchlist;
        $this->movie = $movie;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWatchlist(): Watchlist
    {
        return $this->watchlist;
    }

    public function getMovie(): Movie
    {
        return $this->movie;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}