<?php

namespace App\Entity;

use App\Repository\UserSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
class UserSession
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 128, nullable: false)]
    private ?string $id = null;

    #[ORM\Column(type: Types::BLOB, nullable: false)]
    private $data = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private ?int $lifetime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private ?int $time = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getLifetime(): ?int
    {
        return $this->lifetime;
    }

    public function setLifetime(int $lifetime): self
    {
        $this->lifetime = $lifetime;

        return $this;
    }

    public function getTime(): ?int
    {
        return $this->time;
    }

    public function setTime(int $time): self
    {
        $this->time = $time;

        return $this;
    }
}
