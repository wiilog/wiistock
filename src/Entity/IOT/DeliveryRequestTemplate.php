<?php

namespace App\Entity\IOT;

use App\Entity\Emplacement;
use App\Entity\FreeFieldEntity;
use App\Repository\IOT\DeliveryRequestTemplateRepository;
use App\Entity\Type;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryRequestTemplateRepository::class)
 */
class DeliveryRequestTemplate extends FreeFieldEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="deliveryRequestTemplates")
     */
    private ?Type $type;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name;

    /**
     * @ORM\ManyToOne(targetEntity=TriggerAction::class, inversedBy="deliveryRequestTemplates")
     */
    private ?TriggerAction $triggerAction;

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class, inversedBy="deliveryRequestTemplates")
     */
    private ?Emplacement $destination;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="deliveryRequestTypeTemplates")
     */
    private ?Type $requestType;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type !== $type) {
            $this->type->removeDeliveryRequestTemplate($this);
        }
        $this->type = $type;
        if($type) {
            $type->addDeliveryRequestTemplate($this);
        }

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
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

    public function setTriggerAction(?TriggerAction $triggerAction): self {
        if($this->triggerAction && $this->triggerAction !== $triggerAction) {
            $this->triggerAction->removeDeliveryRequestTemplate($this);
        }
        $this->triggerAction = $triggerAction;
        if($triggerAction) {
            $triggerAction->addDeliveryRequestTemplate($this);
        }

        return $this;
    }

    public function getTriggerAction(): ?TriggerAction {
        return $this->triggerAction;
    }

    public function setDestination(?Emplacement $destination): self {
        if($this->destination && $this->destination !== $destination) {
            $this->destination->removeDeliveryRequestTemplate($this);
        }
        $this->destination = $destination;
        if($destination) {
            $destination->addDeliveryRequestTemplate($this);
        }

        return $this;
    }

    public function getDestination(): ?Emplacement {
        return $this->destination;
    }

    public function setRequestType(?Type $requestType): self {
        if($this->requestType && $this->requestType !== $requestType) {
            $this->requestType->removeDeliveryRequestTypeTemplate($this);
        }
        $this->requestType = $requestType;
        if($requestType) {
            $requestType->addDeliveryRequestTypeTemplate($this);
        }

        return $this;
    }

    public function getRequestType(): ?Type {
        return $this->requestType;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
