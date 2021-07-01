<?php


namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LocationClusterMeterRepository")
 */
class LocationClusterMeter {

    /**
     * @var int|null
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var DateTime|null
     * @ORM\Column(type="date", nullable=false)
     */
    private $date;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true})
     */
    private $dropCounter;

    /**
     * @var LocationCluster|null
     * @ORM\ManyToOne(targetEntity="App\Entity\LocationCluster", inversedBy="metersFrom")
     * @ORM\JoinColumn(nullable=true)
     */
    private $locationClusterFrom;

    /**
     * @var LocationCluster|null
     * @ORM\ManyToOne(targetEntity="App\Entity\LocationCluster", inversedBy="metersInto")
     * @ORM\JoinColumn(nullable=false)
     */
    private $locationClusterInto;

    public function __construct() {
        $this->dropCounter = 0;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @return DateTime|null
     */
    public function getDate(): ?DateTime {
        return $this->date;
    }

    /**
     * @param DateTime|null $date
     * @return self
     */
    public function setDate(?DateTime $date): self {
        $this->date = $date;
        return $this;
    }

    /**
     * @return int
     */
    public function getDropCounter(): int {
        return $this->dropCounter;
    }

    /**
     * @param int $dropCounter
     * @return self
     */
    public function setDropCounter(int $dropCounter): self {
        $this->dropCounter = $dropCounter;
        return $this;
    }

    /**
     * @return self
     */
    public function increaseDropCounter(): self {
        $this->dropCounter += 1;
        return $this;
    }

    /**
     * @return self
     */
    public function decreaseDropCounter(): self {
        if ($this->dropCounter > 0) {
            $this->dropCounter -= 1;
        }
        return $this;
    }

    /**
     * @return LocationCluster|null
     */
    public function getLocationClusterFrom(): ?LocationCluster {
        return $this->locationClusterFrom;
    }

    /**
     * @param LocationCluster|null $locationClusterFrom
     * @return self
     */
    public function setLocationClusterFrom(?LocationCluster $locationClusterFrom): self {
        $this->locationClusterFrom = $locationClusterFrom;
        return $this;
    }

    /**
     * @return LocationCluster|null
     */
    public function getLocationClusterInto(): ?LocationCluster {
        return $this->locationClusterInto;
    }

    /**
     * @param LocationCluster|null $locationClusterInto
     * @return self
     */
    public function setLocationClusterInto(?LocationCluster $locationClusterInto): self {
        $this->locationClusterInto = $locationClusterInto;
        return $this;
    }


}
