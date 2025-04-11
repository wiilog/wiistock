<?php


namespace App\Entity;

use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Repository\LocationClusterRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationClusterRecordRepository::class)]
#[ORM\Index(fields: ["active"])]
class LocationClusterRecord {

    /**
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * @var bool
     */
    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    /**
     * @var Pack|null
     */
    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'locationClusterRecords')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pack $pack = null;

    /**
     * @var TrackingMovement|null
     */
    #[ORM\ManyToOne(targetEntity: TrackingMovement::class, inversedBy: 'firstDropRecords')]
    private ?TrackingMovement $firstDrop = null;

    /**
     * @var TrackingMovement|null
     */
    #[ORM\ManyToOne(targetEntity: TrackingMovement::class, inversedBy: 'lastTrackingRecords')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?TrackingMovement $lastTracking = null;

    #[ORM\ManyToOne(targetEntity: LocationCluster::class, inversedBy: 'locationClusterRecords')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LocationCluster $locationCluster = null;

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

    public function setPack(?Pack $pack): self {
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
     * @return TrackingMovement|null
     */
    public function getFirstDrop(): ?TrackingMovement {
        return $this->firstDrop;
    }

    /**
     * @param TrackingMovement|null $firstDrop
     * @return self
     */
    public function setFirstDrop(?TrackingMovement $firstDrop): self {
        if(isset($this->firstDrop)) {
            $this->firstDrop->removeFirstDropRecord($this);
        }
        $this->firstDrop = $firstDrop;
        if(isset($this->firstDrop)) {
            $this->firstDrop->addFirstDropRecord($this);
        }
        return $this;
    }

    /**
     * @return TrackingMovement|null
     */
    public function getLastTracking(): ?TrackingMovement {
        return $this->lastTracking;
    }

    /**
     * @param TrackingMovement|null $lastTracking
     * @return self
     */
    public function setLastTracking(?TrackingMovement $lastTracking): self {
        if(isset($this->lastTracking)) {
            $this->lastTracking->removeLastTrackingRecord($this);
        }
        $this->lastTracking = $lastTracking;
        if(isset($this->lastTracking)) {
            $this->lastTracking->addLastTrackingRecord($this);
        }
        return $this;
    }

}
