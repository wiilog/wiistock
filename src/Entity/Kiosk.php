<?php

namespace App\Entity;

use App\Repository\KioskTokenRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KioskTokenRepository::class)]
class Kiosk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $token = null;

    #[ORM\Column]
    private ?DateTime $expireAt = null;

    #[ORM\OneToOne(inversedBy: 'kioskToken', targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Utilisateur $user = null;

    #[ORM\Column(type:Types::STRING, nullable:false, unique: true)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'kiosk')]
    private string $pickingType;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getExpireAt(): ?DateTime
    {
        return $this->expireAt;
    }

    public function setExpireAt(DateTime $expireAt): self
    {
        $this->expireAt = $expireAt;

        return $this;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        if($this->user && $this->user->getKioskToken() !== $this) {
            $oldUser = $this->user;
            $this->user = null;
            $oldUser->setKioskToken(null);
        }
        $this->user = $user;
        if($this->user && $this->user->getKioskToken() !== $this) {
            $this->user->setKioskToken($this);
        }

        return $this;
    }
}
