<?php

namespace App\Entity\Transport;

use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Repository\TemperatureRangeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TemperatureRangeRepository::class)]
class TemperatureRange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $value = null;

    #[ORM\ManyToMany(targetEntity: Emplacement::class, mappedBy: 'temperatureRanges')]
    private Collection $locations;

    #[ORM\OneToMany(mappedBy: 'temperatureRange', targetEntity: TransportDeliveryRequestNature::class)]
    private Collection $transportDeliveryRequestNatures;

    #[ORM\ManyToMany(targetEntity: Nature::class, mappedBy: 'temperatureRanges')]
    private Collection $natures;

    public function __construct()
    {
        $this->locations = new ArrayCollection();
        $this->transportDeliveryRequestNatures = new ArrayCollection();
        $this->natures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

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
            $this->locations[] = $location;
            $location->addTemperatureRange($this);
        }

        return $this;
    }

    public function removeLocation(Emplacement $location): self
    {
        if ($this->locations->removeElement($location)) {
            $location->removeTemperatureRange($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportDeliveryRequestNature>
     */
    public function getTransportDeliveryRequestNatures(): Collection
    {
        return $this->transportDeliveryRequestNatures;
    }

    public function addTransportDeliveryRequestNature(TransportDeliveryRequestNature $transportDeliveryRequestNature): self
    {
        if (!$this->transportDeliveryRequestNatures->contains($transportDeliveryRequestNature)) {
            $this->transportDeliveryRequestNatures[] = $transportDeliveryRequestNature;
            $transportDeliveryRequestNature->setTemperatureRange($this);
        }

        return $this;
    }

    public function removeTransportDeliveryRequestNature(TransportDeliveryRequestNature $transportDeliveryRequestNature): self
    {
        if ($this->transportDeliveryRequestNatures->removeElement($transportDeliveryRequestNature)) {
            // set the owning side to null (unless already changed)
            if ($transportDeliveryRequestNature->getTemperatureRange() === $this) {
                $transportDeliveryRequestNature->setTemperatureRange(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Nature>
     */
    public function getNatures(): Collection
    {
        return $this->natures;
    }

    public function addNature(Nature $nature): self
    {
        if (!$this->natures->contains($nature)) {
            $this->natures[] = $nature;
            $nature->addTemperatureRange($this);
        }

        return $this;
    }

    public function removeNature(Nature $nature): self
    {
        if ($this->natures->removeElement($nature)) {
            $nature->removeTemperatureRange($this);
        }

        return $this;
    }
}
