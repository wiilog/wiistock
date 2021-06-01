<?php

namespace App\Entity\IOT;

use App\Repository\IOT\TriggerActionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TriggerActionRepository::class)
 * @ORM\Table(name="`trigger_action`")
 */
class TriggerAction {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="array")
     */
    private ?array $config = [];

    /**
     * @ORM\ManyToOne(targetEntity=AlertTemplate::class, inversedBy="triggerActions")
     */
    private ?AlertTemplate $alertTemplate = null;

    /**
     * @ORM\ManyToOne(targetEntity=RequestTemplate::class, inversedBy="triggerActions")
     */
    private ?RequestTemplate $requestTemplate = null;

    /**
     * @ORM\ManyToOne(targetEntity=SensorWrapper::class, inversedBy="triggerActions")
     */
    private ?SensorWrapper $sensorWrapper = null;

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
        if ($this->requestTemplate && $this->requestTemplate !== $requestTemplate) {
            $this->requestTemplate->removeTriggerAction($this);
        }
        $this->requestTemplate = $requestTemplate;
        if ($requestTemplate) {
            $requestTemplate->addTriggerAction($this);
        }

        return $this;
    }

    public function getAlertTemplate(): ?AlertTemplate {
        return $this->alertTemplate;
    }

    public function setAlertTemplate(?AlertTemplate $alertTemplate): self {
        if ($this->alertTemplate && $this->alertTemplate !== $alertTemplate) {
            $this->alertTemplate->removeTriggerAction($this);
        }
        $this->alertTemplate = $alertTemplate;
        if ($alertTemplate) {
            $alertTemplate->addTriggerAction($this);
        }

        return $this;
    }

    public function getSensorWrapper(): ?SensorWrapper {
        return $this->sensorWrapper;
    }

    public function setSensorWrapper(?SensorWrapper $sensorWrapper): self {
        if ($this->sensorWrapper && $this->sensorWrapper !== $sensorWrapper) {
            $this->sensorWrapper->removeTriggerAction($this);
        }
        $this->sensorWrapper = $sensorWrapper;
        if ($sensorWrapper) {
            $sensorWrapper->addTriggerAction($this);
        }

        return $this;
    }

}
