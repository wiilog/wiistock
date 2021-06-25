<?php

namespace App\Entity\IOT;

use App\Repository\IOT\SensorMessageRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SensorMessageRepository::class)
 */
class SensorMessage
{

    const LOW_TEMPERATURE_THRESHOLD = 10;
    const HIGH_TEMPERATURE_THRESHOLD = 25;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="json")
     */
    private array $payload = [];

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTimeInterface $date = null;

    /**
     * @ORM\ManyToOne(targetEntity=Sensor::class, inversedBy="sensorMessages")
     */
    private ?Sensor $sensor = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $content = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $event = null;

    /**
     * @var Sensor|null
     * @ORM\OneToOne(targetEntity="App\Entity\IOT\Sensor", mappedBy="lastMessage")
     */
    private ?Sensor $linkedSensorLastMessage = null;

    /**
     * @ORM\ManyToMany(targetEntity=Pairing::class, mappedBy="sensorMessages")
     */
    private Collection $pairings;

    public function __construct() {
        $this->pairings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function setSensor(?Sensor $sensor): self {
        if($this->sensor && $this->sensor !== $sensor) {
            $this->sensor->removeSensorMessage($this);
        }
        $this->sensor = $sensor;
        if($sensor) {
            $sensor->addSensorMessage($this);
        }

        return $this;
    }

    public function getSensor(): ?Sensor {
        return $this->sensor;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(string $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getLinkedSensorLastMessage(): ?Sensor {
        return $this->linkedSensorLastMessage;
    }

    public function setLinkedSensorLastMessage(?Sensor $linkedSensorLastMessage): self {
        if($this->linkedSensorLastMessage && $this->linkedSensorLastMessage->getLastMessage() !== $this) {
            $oldLinkedSensorLastMessage = $this->linkedSensorLastMessage;
            $this->linkedSensorLastMessage = null;
            $oldLinkedSensorLastMessage->setLastMessage(null);
        }

        $this->linkedSensorLastMessage = $linkedSensorLastMessage;

        if($this->linkedSensorLastMessage && $this->linkedSensorLastMessage->getLastMessage() !== $this) {
            $this->linkedSensorLastMessage->setLastMessage($this);
        }
        return $this;
    }

    public function getPairings(): Collection {
        return $this->pairings;
    }
}
