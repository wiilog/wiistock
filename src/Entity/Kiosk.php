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

    #[ORM\Column(type: Types::STRING, unique: true, nullable: false)]
    private ?string $token = null ;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $expireAt = null;

    #[ORM\OneToOne(inversedBy: 'kioskToken', targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Utilisateur $user = null;

    #[ORM\Column(type: Types::STRING, unique: true, nullable: false)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $pickingType = null;

    #[ORM\Column(type:Types::STRING, nullable:false)]
    private ?string $subject = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Emplacement $pickingLocation = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $requester = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 1])]
    private ?int $quantityToPick = null;

    #[ORM\Column(type: Types::STRING, nullable: false)]
    private ?String $destination = null;

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
        if($this->user && $this->user->getKiosk() !== $this) {
            $oldUser = $this->user;
            $this->user = null;
            $oldUser->setKiosk(null);
        }
        $this->user = $user;
        if($this->user && $this->user->getKiosk() !== $this) {
            $this->user->setKiosk($this);
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPickingType(): Type
    {
        return $this->pickingType;
    }

    public function setPickingType(Type $pickingType):self
    {
        $this->pickingType = $pickingType;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject):self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getPickingLocation(): ?string
    {
        return $this->pickingLocation;
    }

    public function setPickingLocation(Emplacement $pickingLocation): self
    {
        $this->pickingLocation = $pickingLocation;
        return $this;
    }

    public function getRequester(): ?string
    {
        return $this->requester;
    }

    public function setRequester(Utilisateur $requester): self
    {
        $this->requester = $requester;
        return $this;
    }

    public function getQuantityToPick(): ?int
    {
        return $this->quantityToPick;
    }

    public function setQuantityToPick(int $quantityToPick):self
    {
        $this->quantityToPick = $quantityToPick;
        return $this;
    }

    public function getDestination(): ?int
    {
        return $this->destination;
    }

    public function setDestination(string $destination):self
    {
        $this->destination = $destination;
        return $this;
    }
}
