<?php


namespace App\Entity;

use App\Entity\Tracking\Pack;
use App\Repository\LocationClusterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationClusterRepository::class)]
class LocationCluster {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * @var Collection
     */
    #[ORM\ManyToMany(targetEntity: Emplacement::class, inversedBy: 'clusters')]
    private Collection $locations;

    #[ORM\OneToMany(mappedBy: 'locationCluster', targetEntity: LocationClusterRecord::class, cascade: ['remove'])]
    private Collection $locationClusterRecords;

    #[ORM\OneToMany(mappedBy: 'locationClusterFrom', targetEntity: LocationClusterMeter::class, cascade: ['remove'])]
    private Collection $metersFrom;

    #[ORM\OneToMany(mappedBy: 'locationClusterInto', targetEntity: LocationClusterMeter::class, cascade: ['remove'])]
    private Collection $metersInto;

    #[ORM\ManyToOne(targetEntity: Dashboard\Component::class, inversedBy: 'locationClusters')]
    private ?Dashboard\Component $component = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $clusterKey = null;

    public function __construct() {
        $this->locations = new ArrayCollection();
        $this->locationClusterRecords = new ArrayCollection();
        $this->metersFrom = new ArrayCollection();
        $this->metersInto = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @return Collection
     */
    public function getLocations(): Collection {
        return $this->locations;
    }

    /**
     * @param Emplacement $location
     * @return LocationCluster
     */
    public function addLocation(Emplacement $location): self {
        if(!$this->locations->contains($location)) {
            $this->locations->add($location);
        }
        return $this;
    }

    /**
     * @param Emplacement $location
     * @return LocationCluster
     */
    public function removeLocation(Emplacement $location): self {
        if($this->locations->contains($location)) {
            $this->locations->removeElement($location);
        }
        return $this;
    }

    /**
     * @param bool $onlyActive
     * @return Collection
     */
    public function getLocationClusterRecords(bool $onlyActive = false): Collection {
        return $onlyActive
            ? $this->locationClusterRecords
            : $this->locationClusterRecords
                ->matching(Criteria::create()->where(Criteria::expr()->eq('active', 1)));
    }

    /**
     * @param Pack $pack
     * @return LocationClusterRecord|null
     */
    public function getLocationClusterRecord(Pack $pack): ?LocationClusterRecord {
        $matchingRecord = null;
        if($pack->getId()) {
            /** @var LocationClusterRecord $record */
            foreach($this->locationClusterRecords as $record) {
                $recordPack = $record->getPack();
                if($recordPack
                    && $pack->getId() === $recordPack->getId()) {
                    $matchingRecord = $record;
                    break;
                }
            }
        }
        return $matchingRecord;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return self
     */
    public function addLocationClusterRecord(LocationClusterRecord $locationClusterRecord): self {
        if(!$this->locationClusterRecords->contains($locationClusterRecord)) {
            $this->locationClusterRecords[] = $locationClusterRecord;
            $locationClusterRecord->setLocationCluster($this);
        }
        return $this;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return self
     */
    public function removeLocationClusterRecord(LocationClusterRecord $locationClusterRecord): self {
        if($this->locationClusterRecords->contains($locationClusterRecord)) {
            $this->locationClusterRecords->removeElement($locationClusterRecord);
            // set the owning side to null (unless already changed)
            if($locationClusterRecord->getLocationCluster() === $this) {
                $locationClusterRecord->setLocationCluster(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection
     */
    public function getMetersFrom(): Collection {
        return $this->metersFrom;
    }

    /**
     * @param LocationClusterMeter $meter
     * @return self
     */
    public function addMeterFrom(LocationClusterMeter $meter): self {
        if(!$this->metersFrom->contains($meter)) {
            $this->metersFrom[] = $meter;
            $meter->setLocationClusterFrom($this);
        }
        return $this;
    }

    /**
     * @param LocationClusterMeter $meter
     * @return self
     */
    public function removeMeterFrom(LocationClusterMeter $meter): self {
        if($this->metersFrom->contains($meter)) {
            $this->metersFrom->removeElement($meter);
            // set the owning side to null (unless already changed)
            if($meter->getLocationClusterFrom() === $this) {
                $meter->setLocationClusterFrom(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection
     */
    public function getMetersInto(): Collection {
        return $this->metersInto;
    }

    /**
     * @param LocationClusterMeter $meter
     * @return self
     */
    public function addMeterInto(LocationClusterMeter $meter): self {
        if(!$this->metersInto->contains($meter)) {
            $this->metersInto[] = $meter;
            $meter->setLocationClusterInto($this);
        }
        return $this;
    }

    /**
     * @param LocationClusterMeter $meter
     * @return self
     */
    public function removeMeterInto(LocationClusterMeter $meter): self {
        if($this->metersInto->contains($meter)) {
            $this->metersInto->removeElement($meter);
            // set the owning side to null (unless already changed)
            if($meter->getLocationClusterInto() === $this) {
                $meter->setLocationClusterInto(null);
            }
        }
        return $this;
    }

    /**
     * @return Dashboard\Component|null
     */
    public function getComponent(): ?Dashboard\Component {
        return $this->component;
    }

    /**
     * @param Dashboard\Component|null $component
     * @return self
     */
    public function setComponent(?Dashboard\Component $component): self {

        if($this->component) {
            $this->component->removeLocationCluster($this);
        }

        $this->component = $component;

        if($this->component) {
            $this->component->addLocationCluster($this);
        }

        return $this;
    }

    public function getClusterKey(): ?string {
        return $this->clusterKey;
    }

    public function setClusterKey(?string $clusterKey): self {
        $this->clusterKey = $clusterKey;

        return $this;
    }

}
