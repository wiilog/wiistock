<?php

namespace App\Entity\Transport;

use App\Entity\Emplacement;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorMessageTrait;
use App\Entity\Utilisateur;
use App\Repository\Transport\VehicleRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
class Vehicle implements PairedEntity
{
    use SensorMessageTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $registrationNumber = null;

    #[ORM\OneToOne(inversedBy: 'vehicle', targetEntity: Utilisateur::class)]
    private ?Utilisateur $deliverer = null;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: Emplacement::class)]
    private Collection $locations;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: Pairing::class)]
    private Collection $pairings;

    public function __construct()
    {
        $this->locations = new ArrayCollection();
        $this->pairings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(string $registrationNumber): self
    {
        $this->registrationNumber = $registrationNumber;

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
            $location->setVehicle($this);
        }

        return $this;
    }

    public function removeLocation(Emplacement $location): self
    {
        if ($this->locations->removeElement($location)) {
            // set the owning side to null (unless already changed)
            if ($location->getVehicle() === $this) {
                $location->setVehicle(null);
            }
        }

        return $this;
    }

    public function setLocations(?array $locations): self {
        foreach($this->getLocations()->toArray() as $location) {
            $this->removeLocation($location);
        }

        $this->locations = new ArrayCollection();
        foreach($locations as $location) {
            $this->addLocation($location);
        }

        return $this;
    }

    /**
     * @return Collection<int, Pairing>
     */
    public function getPairings(): Collection
    {
        return $this->pairings;
    }

    public function addPairing(Pairing $pairing): self
    {
        if (!$this->pairings->contains($pairing)) {
            $this->pairings[] = $pairing;
            $pairing->setVehicle($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self
    {
        if ($this->pairings->removeElement($pairing)) {
            // set the owning side to null (unless already changed)
            if ($pairing->getVehicle() === $this) {
                $pairing->setVehicle(null);
            }
        }

        return $this;
    }

    public function getActivePairing(): ?Pairing {
        $criteria = Criteria::create();
        return $this->pairings
            ->matching(
                $criteria
                    ->andWhere(Criteria::expr()->eq('active', true))
                    ->setMaxResults(1)
            )
            ->first() ?: null;
    }

    public function getDeliverer(): ?Utilisateur {
        return $this->deliverer;
    }

    public function setDeliverer(?Utilisateur $deliverer): self {
        if($this->deliverer && $this->deliverer->getVehicle() !== $this) {
            $oldDeliverer = $this->deliverer;
            $this->deliverer = null;
            $oldDeliverer->setDeliverer(null);
        }
        $this->deliverer = $deliverer;
        if($this->deliverer && $this->deliverer->getVehicle() !== $this) {
            $this->deliverer->setVehicle($this);
        }

        return $this;
    }

    public function __toString() {
        return $this->registrationNumber;
    }

    public function getLastPosition(DateTime $start, ?DateTime $end = null): ?string {
        $end = $end ?? new DateTime();
        return Stream::from($this->getSensorMessagesBetween($start, $end,Sensor::GPS ))
            ->sort(function($a, $b) {
                return $a->getDate() < $b->getDate();
            })
            ->first()
            ?->getContent();
    }
}
