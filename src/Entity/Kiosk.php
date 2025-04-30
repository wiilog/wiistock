<?php

namespace App\Entity;

use App\Entity\Type\Type;
use App\Repository\KioskRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KioskRepository::class)]
class Kiosk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, unique: true, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $expireAt = null;

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

    public function setToken(?string $token): self
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

    public function getPickingLocation(): ?Emplacement
    {
        return $this->pickingLocation;
    }

    public function setPickingLocation(Emplacement $pickingLocation): self
    {
        $this->pickingLocation = $pickingLocation;
        return $this;
    }

    public function getRequester(): ?Utilisateur
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

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination):self
    {
        $this->destination = $destination;
        return $this;
    }
}
