<?php

namespace App\Entity\IOT;

use App\Entity\Type;
use App\Repository\IOT\SensorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SensorRepository::class)]
class Sensor {

    const TEMPERATURE = 'Température';
    const GPS = 'GPS';
    const ACTION = 'Action';
    const SENSOR_ICONS = [
        self::TEMPERATURE => 'iot-temperature',
        self::GPS => 'iot-tracking',
    ];
    const LOCATION = 'location';
    const LOCATION_GROUP = 'location-group';
    const PACK = 'pack';
    const ARTICLE = 'article';
    const VEHICLE = 'vehicle';
    const PREPARATION = 'preparation';
    const DELIVERY_REQUEST = 'delivery-request';
    const COLLECT_REQUEST = 'collect-request';
    const COLLECT_ORDER = 'collect-order';
    const PAIRING_CATEGORIES = [
        'Emplacement' => self::LOCATION,
        'Unité logistique' => self::PACK,
        'Article' => self::ARTICLE,
        'Véhicule' => self::VEHICLE,
        'Préparation' => self::PREPARATION,
        'Ordre de collecte' => self::COLLECT_REQUEST,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $code = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'sensors')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $frequency = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $battery = null;

    #[ORM\ManyToOne(targetEntity: SensorProfile::class, inversedBy: 'sensors')]
    private ?SensorProfile $profile = null;

    /**
     * @var null|SensorMessage
     */
    #[ORM\OneToOne(targetEntity: SensorMessage::class, inversedBy: 'linkedSensorLastMessage')]
    #[ORM\JoinColumn(nullable: true)]
    private ?SensorMessage $lastMessage = null;

    #[ORM\OneToMany(targetEntity: SensorMessage::class, mappedBy: 'sensor')]
    private Collection $sensorMessages;

    #[ORM\OneToMany(targetEntity: SensorWrapper::class, mappedBy: 'sensor')]
    private Collection $sensorWrappers;

    public function __construct() {
        $this->sensorMessages = new ArrayCollection();
        $this->sensorWrappers = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(string $code): self {
        $this->code = $code;

        return $this;
    }

    public function getFrequency(): ?string {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self {
        $this->frequency = $frequency;

        return $this;
    }

    public function getProfile(): ?SensorProfile {
        return $this->profile;
    }

    public function setProfile(?SensorProfile $profile): self {
        if($this->profile && $this->profile !== $profile) {
            $this->profile->removeSensor($this);
        }
        $this->profile = $profile;
        if($profile) {
            $profile->addSensor($this);
        }

        return $this;
    }

    public function getSensorMessages(): Collection {
        return $this->sensorMessages;
    }

    public function addSensorMessage(SensorMessage $sensorMessage): self {
        if(!$this->sensorMessages->contains($sensorMessage)) {
            $this->sensorMessages[] = $sensorMessage;
            $sensorMessage->setSensor($this);
        }

        return $this;
    }

    public function removeSensorMessage(SensorMessage $sensorMessage): self {
        if($this->sensorMessages->removeElement($sensorMessage)) {
            if($sensorMessage->getSensor() === $this) {
                $sensorMessage->setSensor(null);
            }
        }

        return $this;
    }

    public function setSensorMessages(?array $sensorMessages): self {
        foreach($this->getSensorMessages()->toArray() as $sensorMessage) {
            $this->removeSensorMessage($sensorMessage);
        }

        $this->sensorMessages = new ArrayCollection();
        foreach($sensorMessages as $sensorMessage) {
            $this->addSensorMessage($sensorMessage);
        }

        return $this;
    }

    public function getSensorWrappers(): Collection {
        return $this->sensorWrappers;
    }

    public function addSensorWrapper(SensorWrapper $sensorWrapper): self {
        if(!$this->sensorWrappers->contains($sensorWrapper)) {
            $this->sensorWrappers[] = $sensorWrapper;
            $sensorWrapper->setSensor($this);
        }

        return $this;
    }

    public function removeSensorWrapper(SensorWrapper $sensorWrapper): self {
        if($this->sensorWrappers->removeElement($sensorWrapper)) {
            if($sensorWrapper->getSensor() === $this) {
                $sensorWrapper->setSensor(null);
            }
        }

        return $this;
    }

    public function setSensorWrappers(?array $sensorWrappers): self {
        foreach($this->getSensorWrappers()->toArray() as $sensorWrapper) {
            $this->removeSensorWrapper($sensorWrapper);
        }

        $this->sensorWrappers = new ArrayCollection();
        foreach($sensorWrappers as $sensorWrapper) {
            $this->addSensorWrapper($sensorWrapper);
        }

        return $this;
    }

    public function getBattery(): ?int {
        return $this->battery;
    }

    public function setBattery($battery): self {
        $this->battery = $battery;
        return $this;
    }

    public function getLastMessage(): ?SensorMessage {
        return $this->lastMessage;
    }

    public function setLastMessage(?SensorMessage $lastMessage): self {
        if($this->lastMessage && $this->lastMessage->getLinkedSensorLastMessage() !== $this) {
            $oldLastMessage = $this->lastMessage;
            $this->lastMessage = null;
            $oldLastMessage->setLinkedSensorLastMessage(null);
        }

        $this->lastMessage = $lastMessage;

        if($this->lastMessage && $this->lastMessage->getLinkedSensorLastMessage() !== $this) {
            $this->lastMessage->setLinkedSensorLastMessage($this);
        }

        return $this;
    }

    public function getAvailableSensorWrapper(): ?SensorWrapper {
        $criteria = Criteria::create();
        $availableWrappers = $this->sensorWrappers
            ->matching(
                $criteria
                    ->andWhere(Criteria::expr()->eq('deleted', false))
                    ->orderBy(['id' => Criteria::DESC])
                    ->setMaxResults(1)
            );
        return $availableWrappers->first() ?: null;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type !== $type) {
            $this->type->removeSensor($this);
        }
        $this->type = $type;
        if($type) {
            $type->addSensor($this);
        }
        return $this;
    }

}
