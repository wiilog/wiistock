<?php

namespace App\Entity\IOT;

use App\Repository\IOT\TriggerActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TriggerActionRepository::class)
 * @ORM\Table(name="`trigger_action`")
 */
class TriggerAction
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
    private array $config = [];

    /**
     * @ORM\ManyToOne(targetEntity=AlertTemplate::class, inversedBy="triggerActions")
     */
    private ?AlertTemplate $alertTemplate;

    /**
     * @ORM\OneToMany(targetEntity=CollectRequestTemplate::class, mappedBy="triggerAction")
     */
    private ArrayCollection $collectRequestTemplates;

    /**
     * @ORM\OneToMany(targetEntity=DeliveryRequestTemplate::class, mappedBy="triggerAction")
     */
    private ArrayCollection $deliveryRequestTemplates;

    /**
     * @ORM\OneToMany(targetEntity=HandlingRequestTemplate::class, mappedBy="triggerAction")
     */
    private ArrayCollection $handlingRequestTemplates;

    /**
     * @ORM\ManyToOne(targetEntity=SensorWrapper::class, inversedBy="triggerActions")
     */
    private ?SensorWrapper $sensorWrapper;

    public function __construct()
    {
        $this->collectRequestTemplates = new ArrayCollection();
        $this->deliveryRequestTemplates = new ArrayCollection();
        $this->handlingRequestTemplates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
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

    public function getCollectRequestTemplates(): Collection {
        return $this->collectRequestTemplates;
    }

    public function addCollectRequestTemplate(CollectRequestTemplate $collectRequestTemplate): self {
        if (!$this->collectRequestTemplates->contains($collectRequestTemplate)) {
            $this->collectRequestTemplates[] = $collectRequestTemplate;
            $collectRequestTemplate->setTriggerAction($this);
        }

        return $this;
    }

    public function removeCollectRequestTemplate(CollectRequestTemplate $collectRequestTemplate): self {
        if ($this->collectRequestTemplates->removeElement($collectRequestTemplate)) {
            if ($collectRequestTemplate->getTriggerAction() === $this) {
                $collectRequestTemplate->setTriggerAction(null);
            }
        }

        return $this;
    }

    public function setCollectRequestTemplates(?array $collectRequestTemplates): self {
        foreach($this->getCollectRequestTemplates()->toArray() as $collectRequestTemplate) {
            $this->removeCollectRequestTemplate($collectRequestTemplate);
        }

        $this->collectRequestTemplates = new ArrayCollection();
        foreach($collectRequestTemplates as $collectRequestTemplate) {
            $this->addCollectRequestTemplate($collectRequestTemplate);
        }

        return $this;
    }

    public function getDeliveryRequestTemplates(): Collection {
        return $this->deliveryRequestTemplates;
    }

    public function addDeliveryRequestTemplate(DeliveryRequestTemplate $deliveryRequestTemplate): self {
        if (!$this->deliveryRequestTemplates->contains($deliveryRequestTemplate)) {
            $this->deliveryRequestTemplates[] = $deliveryRequestTemplate;
            $deliveryRequestTemplate->setTriggerAction($this);
        }

        return $this;
    }

    public function removeDeliveryRequestTemplate(DeliveryRequestTemplate $deliveryRequestTemplate): self {
        if ($this->deliveryRequestTemplates->removeElement($deliveryRequestTemplate)) {
            if ($deliveryRequestTemplate->getTriggerAction() === $this) {
                $deliveryRequestTemplate->setTriggerAction(null);
            }
        }

        return $this;
    }

    public function setDeliveryRequestTemplates(?array $deliveryRequestTemplates): self {
        foreach($this->getDeliveryRequestTemplates()->toArray() as $deliveryRequestTemplate) {
            $this->removeDeliveryRequestTemplate($deliveryRequestTemplate);
        }

        $this->deliveryRequestTemplates = new ArrayCollection();
        foreach($deliveryRequestTemplates as $deliveryRequestTemplate) {
            $this->addDeliveryRequestTemplate($deliveryRequestTemplate);
        }

        return $this;
    }

    public function getHandlingRequestTemplates(): Collection {
        return $this->handlingRequestTemplates;
    }

    public function addHandlingRequestTemplate(HandlingRequestTemplate $handlingRequestTemplate): self {
        if (!$this->handlingRequestTemplates->contains($handlingRequestTemplate)) {
            $this->handlingRequestTemplates[] = $handlingRequestTemplate;
            $handlingRequestTemplate->setTriggerAction($this);
        }

        return $this;
    }

    public function removeHandlingRequestTemplate(HandlingRequestTemplate $handlingRequestTemplate): self {
        if ($this->handlingRequestTemplates->removeElement($handlingRequestTemplate)) {
            if ($handlingRequestTemplate->getTriggerAction() === $this) {
                $handlingRequestTemplate->setTriggerAction(null);
            }
        }

        return $this;
    }

    public function setHandlingRequestTemplates(?array $handlingRequestTemplates): self {
        foreach($this->getHandlingRequestTemplates()->toArray() as $handlingRequestTemplate) {
            $this->removeHandlingRequestTemplate($handlingRequestTemplate);
        }

        $this->handlingRequestTemplates = new ArrayCollection();
        foreach($handlingRequestTemplates as $handlingRequestTemplate) {
            $this->addHandlingRequestTemplate($handlingRequestTemplate);
        }

        return $this;
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
}
