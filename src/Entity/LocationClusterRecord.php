<?php


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LocationClusterRepository")
 */
class LocationClusterRecord {

    /**
     * @var int|null
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    private $active;

    /**
     * @var Pack|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Pack", inversedBy="locationClusterRecords")
     * @ORM\JoinColumn (nullable=false)
     */
    private $pack;

    /**
     * @var MouvementTraca|null
     * @ORM\ManyToOne(targetEntity="App\Entity\MouvementTraca", inversedBy="firstDropRecords")
     */
    private $firstDrop;

    /**
     * @var MouvementTraca|null
     * @ORM\ManyToOne(targetEntity="App\Entity\MouvementTraca", inversedBy="lastTrackingRecords")
     */
    private $lastTracking;

    /**
     * @var LocationCluster|null
     * @ORM\ManyToOne(targetEntity="App\Entity\LocationCluster", inversedBy="locationClusterRecords")
     * @ORM\JoinColumn(nullable=false)
     */
    private $locationCluster;

    public function __construct() {
        $this->active = true;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @return Pack|null
     */
    public function getPack(): ?Pack {
        return $this->pack;
    }

    /**
     * @param Pack $pack
     * @return self
     */
    public function setPack(Pack $pack): self {
        $this->pack = $pack;
        return $this;
    }

    /**
     * @return LocationCluster|null
     */
    public function getLocationCluster(): ?Pack {
        return $this->locationCluster;
    }

    /**
     * @param LocationCluster $locationCluster
     * @return self
     */
    public function setLocationCluster(LocationCluster $locationCluster): self {
        $this->locationCluster = $locationCluster;
        return $this;
    }

    /**
     * @return bool
     */
    public function isActive(): bool {
        return $this->active;
    }

    /**
     * @param bool $active
     * @return $this
     */
    public function setActive(bool $active): self {
        $this->active = $active;
        return $this;
    }

    /**
     * @return MouvementTraca|null
     */
    public function getFirstDrop(): ?MouvementTraca {
        return $this->firstDrop;
    }

    /**
     * @param MouvementTraca|null $firstDrop
     * @return self
     */
    public function setFirstDrop(?MouvementTraca $firstDrop): self {
        if (isset($this->firstDrop)) {
            $this->firstDrop->removeFirstDropRecord($this);
        }
        $this->firstDrop = $firstDrop;
        if (isset($this->firstDrop)) {
            $this->firstDrop->addFirstDropRecord($this);
        }
        return $this;
    }

    /**
     * @return MouvementTraca|null
     */
    public function getLastTracking(): ?MouvementTraca {
        return $this->lastTracking;
    }

    /**
     * @param MouvementTraca|null $lastTracking
     * @return self
     */
    public function setLastTracking(?MouvementTraca $lastTracking): self {
        if (isset($this->lastTracking)) {
            $this->lastTracking->removeLastTrackingRecord($this);
        }
        $this->lastTracking = $lastTracking;
        if (isset($this->lastTracking)) {
            $this->lastTracking->addLastTrackingRecord($this);
        }
        return $this;
    }
}
