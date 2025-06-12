<?php

namespace App\Entity\Security;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass()]
abstract class Token {
    public const TOKEN_DEFAULT_LENGTH = 32;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $expireAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    private ?string $token = null;

    public function __construct(string $plainToken) {
        $this->plainToken = $plainToken;
        $this->token = hash('sha256', $plainToken);
    }

    private ?string $plainToken = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getExpireAt(): ?DateTime {
        return $this->expireAt;
    }

    public function setExpireAt(DateTime $expireAt): self {
        $this->expireAt = $expireAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getToken(): ?string {
        return $this->token;
    }

    public function getPlainToken(): ?string {
        return $this->plainToken;
    }
}
