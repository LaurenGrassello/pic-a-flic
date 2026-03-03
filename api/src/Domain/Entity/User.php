<?php
declare(strict_types=1);

namespace PicaFlic\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * User entity represents an application user.
 * - Passwords are Argon2id hashes.
 * - Email is unique.
 */
#[ORM\Entity, ORM\Table(name: "users")]
class User
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    private string $email;

    // map to password_hash
    #[ORM\Column(name: "password_hash", type: "string", length: 255)]
    private string $passwordHash;

    // map to display_name
    #[ORM\Column(name: "display_name", type: "string", length: 100)]
    private string $displayName;

    public function __construct(string $email, string $passwordHash, string $displayName)
    {
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->displayName = $displayName;
    }

    public function getId(): int { 
      return $this->id; 
    }

    public function getEmail(): string { 
      return $this->email; 
    }

       public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    public function getDisplayName(): string { 
      return $this->displayName; 
    }

    public function setDisplayName(string $name): void { 
      $this->displayName = $name; 
    }

}