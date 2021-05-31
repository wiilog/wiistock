<?php

namespace App\Entity\IOT;

use App\Repository\IOT\SensorProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SensorProfileRepository::class)
 */
class SensorProfile
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $maxTriggers;

    /**
     * @ORM\OneToMany(targetEntity=Sensor::class, mappedBy="profile")
     */
    private ArrayCollection $sensors;

    public function __construct()
    {
        $this->sensors = new ArrayCollection();
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

    public function getMaxTriggers(): ?int
    {
        return $this->maxTriggers;
    }

    public function setMaxTriggers(int $maxTriggers): self
    {
        $this->maxTriggers = $maxTriggers;

        return $this;
    }

    public function getSensors(): Collection {
        return $this->sensors;
    }

    public function addSensor(Sensor $sensor): self {
        if (!$this->sensors->contains($sensor)) {
            $this->sensors[] = $sensor;
            $sensor->setProfile($this);
        }

        return $this;
    }

    public function removeSensor(Sensor $sensor): self {
        if ($this->sensors->removeElement($sensor)) {
            if ($sensor->getProfile() === $this) {
                $sensor->setProfile(null);
            }
        }

        return $this;
    }

    public function setSensors(?array $sensors): self {
        foreach($this->getSensors()->toArray() as $sensor) {
            $this->removeSensor($sensor);
        }

        $this->sensors = new ArrayCollection();
        foreach($sensors as $sensor) {
            $this->addSensor($sensor);
        }

        return $this;
    }
}
