<?php

namespace App\Entity\IOT;

use App\Entity\FreeFieldEntity;
use App\Repository\IOT\SensorWrapperRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utilisateur;

/**
 * @ORM\Entity(repositoryClass=SensorWrapperRepository::class)
 */
class SensorWrapper extends FreeFieldEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Sensor::class, inversedBy="sensorWrappers")
     */
    private ?Sensor $sensor = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default": false})
     */
    private bool $deleted = false;

    /**
     * @ORM\ManyToOne(targetEntity=Utilisateur::class, inversedBy="sensorWrappers")
     */
    private ?Utilisateur $manager = null;

    /**
     * @ORM\OneToMany(targetEntity=Pairing::class, mappedBy="sensorWrapper")
     */
    private Collection $pairings;

    /**
     * @ORM\OneToMany(targetEntity=TriggerAction::class, mappedBy="sensorWrapper")
     */
    private Collection $triggerActions;

    public function __construct()
    {
        $this->pairings = new ArrayCollection();
        $this->triggerActions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSensor(): ?Sensor {
        return $this->sensor;
    }

    public function setSensor(?Sensor $sensor): self {
        if($this->sensor && $this->sensor !== $sensor) {
            $this->sensor->removeSensorWrapper($this);
        }
        $this->sensor = $sensor;
        if($sensor) {
            $sensor->addSensorWrapper($this);
        }

        return $this;
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

    public function getManager(): ?Utilisateur {
        return $this->manager;
    }

    public function setManager(?Utilisateur $manager): self {
        if($this->manager && $this->manager !== $manager) {
            $this->manager->removeSensorWrapper($this);
        }
        $this->manager = $manager;
        if($manager) {
            $manager->addSensorWrapper($this);
        }

        return $this;
    }

    /**
     * @return Collection|Pairing[]
     */
    public function getPairings(): Collection {
        return $this->pairings;
    }

    public function addPairing(Pairing $pairing): self {
        if (!$this->pairings->contains($pairing)) {
            $this->pairings[] = $pairing;
            $pairing->setSensorWrapper($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self {
        if ($this->pairings->removeElement($pairing)) {
            if ($pairing->getSensorWrapper() === $this) {
                $pairing->setSensorWrapper(null);
            }
        }

        return $this;
    }

    public function setPairings(?array $pairings): self {
        foreach($this->getPairings()->toArray() as $pairing) {
            $this->removePairing($pairing);
        }

        $this->pairings = new ArrayCollection();
        foreach($pairings as $pairing) {
            $this->addPairing($pairing);
        }

        return $this;
    }

    /**
     * @return Collection|TriggerAction[]
     */
    public function getTriggerActions(): Collection {
        return $this->triggerActions;
    }

    public function addTriggerAction(TriggerAction $triggerAction): self {
        if (!$this->triggerActions->contains($triggerAction)) {
            $this->triggerActions[] = $triggerAction;
            $triggerAction->setSensorWrapper($this);
        }

        return $this;
    }

    public function removeTriggerAction(TriggerAction $triggerAction): self {
        if ($this->triggerActions->removeElement($triggerAction)) {
            if ($triggerAction->getSensorWrapper() === $this) {
                $triggerAction->setSensorWrapper(null);
            }
        }

        return $this;
    }

    public function setTriggerActions(?array $triggerActions): self {
        foreach($this->getTriggerActions()->toArray() as $triggerAction) {
            $this->removeTriggerAction($triggerAction);
        }

        $this->triggerActions = new ArrayCollection();
        foreach($triggerActions as $triggerAction) {
            $this->addTriggerAction($triggerAction);
        }

        return $this;
    }

    public function isDeleted(): bool {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self {
        $this->deleted = $deleted;
        return $this;
    }
}
