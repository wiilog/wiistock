<?php

namespace App\Entity\IOT;

use App\Repository\IOT\SensorMessageRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SensorMessageRepository::class)
 */
class SensorMessage
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="array")
     */
    private array $payload = [];

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTimeInterface $date;

    /**
     * @ORM\ManyToOne(targetEntity=Sensor::class, inversedBy="sensorMessages")
     */
    private ?Sensor $sensor;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $content;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $event;

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
}
