<?php

namespace App\Entity\Security;

use App\Repository\Security\TokenRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TokenRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
abstract class Token {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private ?DateTimeImmutable $expireAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $token = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getExpireAt(): ?DateTimeImmutable {
        return $this->expireAt;
    }

    public function setExpireAt(DateTimeImmutable $expireAt): self {
        $this->expireAt = $expireAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getToken(): ?string {
        return $this->token;
    }

    public function setToken(string $token): self {
        $this->token = $token;

        return $this;
    }
}
