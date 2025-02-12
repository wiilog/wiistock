<?php

namespace App\Entity\IOT;

use App\Entity\RequestTemplate\RequestTemplate;
use App\Repository\IOT\TriggerActionRepository;
use App\Service\IOT\IOTService;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: TriggerActionRepository::class)]
#[ORM\Table(name: '`trigger_action`')]
class TriggerAction {

    const REQUEST = "request";
    const ALERT = "alert";
    const DROP_ON_LOCATION = "dropOnLocation";
    const TEMPLATE_TYPES = [
        self::REQUEST => "Demande",
        self::ALERT => "Alerte",
    ];
    const LOWER = "lower";
    const HIGHER = "higher";

    const COMPARATORS = [
        self::LOWER => "Inférieure",
        self::HIGHER => "Supérieure",
    ];

    const ACTION_TYPE_TEMPERATURE = "temperature";
    const ACTION_TYPE_HYGROMETRY = "hygrometry";
    const ACTION_TYPE_ZONE_ENTER = "zone_enter";
    const ACTION_TYPE_ZONE_EXIT = "zone_exit";

    const ACTION_TYPE_ACTION = "action";
    const ACTION_DATA_TYPES = [
        self::ACTION_TYPE_TEMPERATURE => IOTService::DATA_TYPE_TEMPERATURE,
        self::ACTION_TYPE_HYGROMETRY => IOTService::DATA_TYPE_HYGROMETRY,
        self::ACTION_TYPE_ZONE_ENTER => IOTService::DATA_TYPE_ZONE_ENTER,
        self::ACTION_TYPE_ZONE_EXIT => IOTService::DATA_TYPE_ZONE_EXIT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'json')]
    private ?array $config = [];

    #[ORM\ManyToOne(targetEntity: AlertTemplate::class, inversedBy: 'triggerActions')]
    private ?AlertTemplate $alertTemplate = null;

    #[ORM\ManyToOne(targetEntity: RequestTemplate::class, inversedBy: 'triggerActions')]
    private ?RequestTemplate $requestTemplate = null;

    #[ORM\ManyToOne(targetEntity: SensorWrapper::class, inversedBy: 'triggerActions')]
    private ?SensorWrapper $sensorWrapper = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $lastTrigger = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getConfig(): ?array {
        return $this->config;
    }

    public function setConfig(array $config): self {
        $this->config = $config;

        return $this;
    }

    public function getRequestTemplate(): ?RequestTemplate {
        return $this->requestTemplate;
    }

    public function setRequestTemplate(?RequestTemplate $requestTemplate): self {
        if($this->requestTemplate && $this->requestTemplate !== $requestTemplate) {
            $this->requestTemplate->removeTriggerAction($this);
        }
        $this->requestTemplate = $requestTemplate;
        if($requestTemplate) {
            $requestTemplate->addTriggerAction($this);
        }

        return $this;
    }

    public function isRequest(): bool {
        return $this->getRequestTemplate() != null;
    }

    public function getAlertTemplate(): ?AlertTemplate {
        return $this->alertTemplate;
    }

    public function setAlertTemplate(?AlertTemplate $alertTemplate): self {
        if($this->alertTemplate && $this->alertTemplate !== $alertTemplate) {
            $this->alertTemplate->removeTriggerAction($this);
        }
        $this->alertTemplate = $alertTemplate;
        if($alertTemplate) {
            $alertTemplate->addTriggerAction($this);
        }

        return $this;
    }

    public function isAlert(): bool {
        return $this->getAlertTemplate() != null;
    }

    public function getSensorWrapper(): ?SensorWrapper {
        return $this->sensorWrapper;
    }

    public function setSensorWrapper(?SensorWrapper $sensorWrapper): self {
        if($this->sensorWrapper && $this->sensorWrapper !== $sensorWrapper) {
            $this->sensorWrapper->removeTriggerAction($this);
        }
        $this->sensorWrapper = $sensorWrapper;
        if($sensorWrapper) {
            $sensorWrapper->addTriggerAction($this);
        }

        return $this;
    }

    public function getLastTrigger(): ?DateTimeInterface {
        return $this->lastTrigger;
    }

    public function setLastTrigger(DateTimeInterface $lastTrigger): self {
        $this->lastTrigger = $lastTrigger;

        return $this;
    }

    public function getActionType(): ?string {
        return Stream::from($this->getConfig())
            ->findKey(static fn($value, $key) => isset(self::ACTION_DATA_TYPES[$key]));
    }
}
