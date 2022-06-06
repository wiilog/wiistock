<?php

namespace App\Entity;

use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorMessageTrait;
use App\Repository\LocationGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationGroupRepository::class)]
class LocationGroup implements PairedEntity {

    use SensorMessageTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $label;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'boolean')]
    private ?bool $active;

    #[ORM\OneToMany(targetEntity: Emplacement::class, mappedBy: 'locationGroup')]
    private Collection $locations;

    #[ORM\OneToMany(targetEntity: Pairing::class, mappedBy: 'locationGroup', cascade: ['remove'])]
    private Collection $pairings;

    #[ORM\OneToMany(targetEntity: Utilisateur::class, mappedBy: 'locationGroupDropzone')]
    private Collection $users;

    public function __construct() {
        $this->locations = new ArrayCollection();
        $this->sensorMessages = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;

        return $this;
    }

    public function isActive(): ?string {
        return $this->active;
    }

    public function setActive(?string $active): self {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection|Emplacement[]
     */
    public function getLocations(): Collection {
        return $this->locations;
    }

    public function addLocation(Emplacement $location): self {
        if(!$this->locations->contains($location)) {
            $this->locations[] = $location;
            $location->setLocationGroup($this);
        }

        return $this;
    }

    public function removeLocation(Emplacement $location): self {
        if($this->locations->removeElement($location)) {
            if($location->getLocationGroup() === $this) {
                $location->setLocationGroup(null);
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
     * @return Collection|Pairing[]
     */
    public function getPairings(): Collection {
        return $this->pairings;
    }

    /**
     * @return Collection|Pairing[]
     */
    public function getActivePairings(): Collection {
        $criteria = Criteria::create();
        return $this->pairings->matching($criteria->andWhere(Criteria::expr()->eq('active', true)));
    }

    public function addPairing(Pairing $pairing): self {
        if(!$this->pairings->contains($pairing)) {
            $this->pairings[] = $pairing;
            $pairing->setLocationGroup($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self {
        if($this->pairings->removeElement($pairing)) {
            // set the owning side to null (unless already changed)
            if($pairing->getLocationGroup() === $this) {
                $pairing->setLocationGroup(null);
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

    public function __toString(): string {
        return $this->getLabel();
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getUsers(): Collection {
        return $this->users;
    }

    public function addUser(Utilisateur $user): self {
        if(!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setLocationGroupDropzone($this);
        }

        return $this;
    }

    public function removeUser(Utilisateur $user): self {
        if($this->users->removeElement($user)) {
            if($user->getLocationGroupDropzone() === $this) {
                $user->setLocationGroupDropzone(null);
            }
        }

        return $this;
    }

    public function setUsers(?array $users): self {
        foreach($this->getUsers()->toArray() as $user) {
            $this->removeUser($user);
        }

        $this->users = new ArrayCollection();
        foreach($users as $user) {
            $this->addUser($user);
        }

        return $this;
    }

}
