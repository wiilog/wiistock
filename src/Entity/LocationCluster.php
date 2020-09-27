<?php


namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LocationClusterRepository")
 */
class LocationCluster {

    public const CLUSTER_CODE_ADMIN_DASHBOARD_1 = 'CLUSTER_CODE_ADMIN_DASHBOARD_1';
    public const CLUSTER_CODE_ADMIN_DASHBOARD_2 = 'CLUSTER_CODE_ADMIN_DASHBOARD_2';

    /**
     * @var int|null
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $code;

    /**
     * @var Collection
     * @ORM\ManyToMany(targetEntity="App\Entity\Emplacement", inversedBy="clusters")
     */
    private $locations;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LocationClusterRecord", mappedBy="locationCluster", orphanRemoval=true)
     */
    private $locationClusterRecords;

    public function __construct() {
        $this->locations = new ArrayCollection();
        $this->locationClusterRecords = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCode(): string {
        return $this->code;
    }

    /**
     * @param string $code
     * @return self
     */
    public function setCode(string $code): self {
        $this->code = $code;
        return $this;
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
        if (!$this->locations->contains($location)) {
            $this->locations->add($location);
        }
        return $this;
    }

    /**
     * @param Emplacement $location
     * @return LocationCluster
     */
    public function removeLocation(Emplacement $location): self {
        if ($this->locations->contains($location)) {
            $this->locations->removeElement($location);
        }
        return $this;
    }

    /**
     * @return Collection
     */
    public function getLocationClusterRecords(): Collection {
        return $this->locationClusterRecords;
    }

    /**
     * @param Pack $pack
     * @return LocationClusterRecord|null
     */
    public function getLocationClusterRecord(Pack $pack): ?LocationClusterRecord {
        $matchingRecord = null;
        /** @var LocationClusterRecord $record */
        foreach ($this->locationClusterRecords as $record) {
            $recordPack = $record->getPack();
            if ($recordPack
                && $pack->getId() === $recordPack->getId()) {
                $matchingRecord = $record;
                break;
            }
        }
        return $matchingRecord;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return self
     */
    public function addLocationClusterRecord(LocationClusterRecord $locationClusterRecord): self {
        if (!$this->locationClusterRecords->contains($locationClusterRecord)) {
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
        if ($this->locationClusterRecords->contains($locationClusterRecord)) {
            $this->locationClusterRecords->removeElement($locationClusterRecord);
            // set the owning side to null (unless already changed)
            if ($locationClusterRecord->getLocationCluster() === $this) {
                $locationClusterRecord->setLocationCluster(null);
            }
        }
        return $this;
    }
}
