<?php

namespace App\Entity\RequestTemplate;

use Doctrine\ORM\Mapping as ORM;


#[ORM\MappedSuperclass()]
abstract class RequestTemplateLine {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeliveryRequestTemplateTriggerAction::class, inversedBy: 'lines')]
    private ?DeliveryRequestTemplateTriggerAction $deliveryRequestTemplateTriggerAction = null;

    #[ORM\ManyToOne(targetEntity: CollectRequestTemplate::class, inversedBy: 'lines')]
    private ?CollectRequestTemplate $collectRequestTemplate = null;

    #[ORM\Column(type: 'integer')]
    private ?int $quantityToTake = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDeliveryRequestTemplateTriggerAction(): ?DeliveryRequestTemplateTriggerAction {
        return $this->deliveryRequestTemplateTriggerAction;
    }

    public function setDeliveryRequestTemplateTriggerAction(?DeliveryRequestTemplateTriggerAction $requestTemplate): self {
        $this->deliveryRequestTemplateTriggerAction = $requestTemplate;

        return $this;
    }

    public function getCollectRequestTemplate(): ?CollectRequestTemplate {
        return $this->collectRequestTemplate;
    }

    public function setCollectRequestTemplate(?CollectRequestTemplate $requestTemplate): self {
        $this->collectRequestTemplate = $requestTemplate;

        return $this;
    }

    public function setRequestTemplate(?RequestTemplate $requestTemplate): self {
        if($requestTemplate instanceof DeliveryRequestTemplateTriggerAction) {
            $this->setDeliveryRequestTemplateTriggerAction($requestTemplate);
        } else if($requestTemplate instanceof CollectRequestTemplate) {
            $this->setCollectRequestTemplate($requestTemplate);
        }

        return $this;
    }

    public function getQuantityToTake(): ?int {
        return $this->quantityToTake;
    }

    public function setQuantityToTake(int $quantityToTake): self {
        $this->quantityToTake = $quantityToTake;

        return $this;
    }

}
