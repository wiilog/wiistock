<?php

namespace App\Entity\Transport;

use App\Entity\Emplacement;
use App\Entity\Utilisateur;
use App\Repository\VehicleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $registration = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'vehicles')]
    private ?Utilisateur $deliverer = null;

    #[ORM\OneToOne(inversedBy: 'vehicle', targetEntity: Emplacement::class, cascade: ['persist', 'remove'])]
    private ?Emplacement $location = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRegistration(): ?string
    {
        return $this->registration;
    }

    public function setRegistration(string $registration): self
    {
        $this->registration = $registration;

        return $this;
    }

    public function getDeliverer(): ?Utilisateur
    {
        return $this->deliverer;
    }

    public function setDeliverer(?Utilisateur $deliverer): self {
        if($this->deliverer && $this->deliverer !== $deliverer) {
            $this->deliverer->removeVehicle($this);
        }
        $this->deliverer = $deliverer;
        $deliverer?->addVehicle($this);

        return $this;
    }

    public function getLocation(): ?Emplacement
    {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self
    {
        $this->location = $location;

        return $this;
    }
}
