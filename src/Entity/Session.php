<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
class Session
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $openedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $closedAt = null;

    /*
     * Correspond à l'ancien api_key de l’utilisateur si session nomade
     * ou au session_id de symfony pour la session web
     * */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $sessionKey = null;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOpenedAt(): ?\DateTime
    {
        return $this->openedAt;
    }

    public function setOpenedAt(\DateTime $openedAt): self
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getClosedAt(): ?\DateTime
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTime $closedAt): self
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getSessionKey(): ?string
    {
        return $this->sessionKey;
    }

    public function setSessionKey(string $sessionKey): self
    {
        $this->sessionKey = $sessionKey;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        if($this->user && $this->user !== $user) {
            $this->user->removeSession($this);
        }
        $this->user = $user;
        $user?->addSession($this);

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }
}
