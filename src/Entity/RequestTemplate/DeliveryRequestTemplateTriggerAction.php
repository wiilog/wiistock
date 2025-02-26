<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\RequestTemplate\DeliveryRequestTemplateRepositoryTriggerAction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestTemplateRepositoryTriggerAction::class)]
class DeliveryRequestTemplateTriggerAction extends RequestTemplate implements DeliveryRequestTemplateInterface {

    const DELIVERY_REQUEST_TEMPLATE_USAGE = [
        DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION->value => "Actionneur",
        DeliveryRequestTemplateUsageEnum::SLEEPING_STOCK->value => "Stock dormant",
    ];

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $destination = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToMany(mappedBy: 'deliveryRequestTemplateTriggerAction', targetEntity: RequestTemplateLine::class, cascade: ["remove"])]
    private Collection $lines;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $buttonIcon = null;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: DeliveryRequestTemplateUsageEnum::class, options: ["default" => DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION])]
    private ?DeliveryRequestTemplateUsageEnum $deliveryRequestTemplateType;

    public function __construct() {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    public function getDestination(): ?Emplacement {
        return $this->destination;
    }

    public function setDestination(?Emplacement $destination): self {
        $this->destination = $destination;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection<RequestTemplateLine>
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(ReferenceArticle $ref): self {
        if(!$this->lines->contains($ref)) {
            $this->lines[] = $ref;
        }

        return $this;
    }

    public function removeLine(ReferenceArticle $ref): self {
        $this->lines->removeElement($ref);

        return $this;
    }

    public function getButtonIcon(): ?Attachment {
        return $this->buttonIcon;
    }

    public function setButtonIcon(?Attachment $buttonIcon): self {
        $this->buttonIcon = $buttonIcon;

        return $this;
    }

    public function getDeliveryRequestTemplateType(): ?DeliveryRequestTemplateUsageEnum {
        return $this->deliveryRequestTemplateType;
    }

    public function setDeliveryRequestTemplateType(?DeliveryRequestTemplateUsageEnum $deliveryRequestTemplateType): self {
        $this->deliveryRequestTemplateType = $deliveryRequestTemplateType;
        return $this;
    }

    public function getUsage(): DeliveryRequestTemplateUsageEnum {
        return DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION;
    }
}
