<?php

namespace App\Entity;

use App\Repository\ZoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZoneRepository::class)]
class Zone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $inventoryIndicator = null;

    #[ORM\OneToMany(mappedBy: 'zone', targetEntity: Emplacement::class)]
    private Collection $locations;

    public function __construct()
    {
        $this->locations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getInventoryIndicator(): ?float
    {
        return $this->inventoryIndicator;
    }

    public function setInventoryIndicator(?float $inventoryIndicator): self
    {
        $this->inventoryIndicator = $inventoryIndicator;

        return $this;
    }

    /**
     * @return Collection<int, Emplacement>
     */
    public function getLocations(): Collection
    {
        return $this->locations;
    }

    public function addLocation(Emplacement $location): self
    {
        if (!$this->locations->contains($location)) {
            $this->locations->add($location);
            $location->setZone($this);
        }

        return $this;
    }

    public function removeLocation(Emplacement $location): self
    {
        if ($this->locations->removeElement($location)) {
            if ($location->getZone() === $this) {
                $location->setZone(null);
            }
        }

        return $this;
    }

    public function setLocations(?iterable $locations): self {
        foreach($this->getLocations()->toArray() as $location) {
            $this->removeLocation($location);
        }

        $this->locations = new ArrayCollection();
        foreach($locations ?? [] as $location) {
            $this->addLocation($location);
        }

        return $this;
    }
}
