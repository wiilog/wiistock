<?php

namespace App\Entity\RequestTemplate;

use App\Entity\ReferenceArticle;
use App\Repository\RequestTemplate\RequestTemplateLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestTemplateLineRepository::class)]
class RequestTemplateLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeliveryRequestTemplateTriggerAction::class, inversedBy: 'lines')]
    private ?DeliveryRequestTemplateTriggerAction $deliveryRequestTemplate = null;

    #[ORM\ManyToOne(targetEntity: CollectRequestTemplate::class, inversedBy: 'lines')]
    private ?CollectRequestTemplate $collectRequestTemplate = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'requestTemplateLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReferenceArticle $reference = null;

    #[ORM\Column(type: 'integer')]
    private ?int $quantityToTake = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDeliveryRequestTemplate(): ?DeliveryRequestTemplateTriggerAction {
        return $this->deliveryRequestTemplate;
    }

    public function setDeliveryRequestTemplate(?DeliveryRequestTemplateTriggerAction $requestTemplate): self {
        $this->deliveryRequestTemplate = $requestTemplate;

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
            $this->setDeliveryRequestTemplate($requestTemplate);
        } else if($requestTemplate instanceof CollectRequestTemplate) {
            $this->setCollectRequestTemplate($requestTemplate);
        }

        return $this;
    }

    public function getReference(): ?ReferenceArticle {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self {
        $this->reference = $reference;

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
