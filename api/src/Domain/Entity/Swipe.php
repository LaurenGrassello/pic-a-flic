<?php
declare (strict_types = 1);

namespace PicaFlic\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: "swipe")]
class Swipe
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Movie::class)]
    #[ORM\JoinColumn(name: "movie_id", nullable: false)]
    private Movie $movie;

    #[ORM\Column(type: "boolean")]
    private bool $liked;

    #[ORM\Column(name: "created_at", type: "datetime")]
    private \DateTimeInterface $createdAt;

    public function __construct(User $user, Movie $movie, bool $liked)
    {
        $this->user = $user;
        $this->movie = $movie;
        $this->liked = $liked;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMovie(): Movie
    {
        return $this->movie;
    }

    public function isLiked(): bool
    {
        return $this->liked;
    }
}