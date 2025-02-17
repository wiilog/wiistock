<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\RequestTemplate\DeliveryRequestTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestTemplateRepository::class)]
class DeliveryRequestTemplate extends RequestTemplate {

    const DELIVERY_REQUEST_TEMPLATE_TYPES = [
        DeliveryRequestTemplateTypeEnum::TRIGGER_ACTION->value => "Avec Référence",
        DeliveryRequestTemplateTypeEnum::SLEEPING_STOCK->value => "Sans Référence",
    ];

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'deliveryRequestTemplates')]
    private ?Emplacement $destination = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToMany(mappedBy: 'deliveryRequestTemplate', targetEntity: RequestTemplateLine::class, cascade: ["remove"])]
    private Collection $lines;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $buttonIcon = null;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: DeliveryRequestTemplateTypeEnum::class, options: ["default" => DeliveryRequestTemplateTypeEnum::TRIGGER_ACTION])]
    private ?DeliveryRequestTemplateTypeEnum $deliveryRequestTemplateType;

    public function __construct() {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    public function getDestination(): ?Emplacement {
        return $this->destination;
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

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection|RequestTemplateLine[]
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

    public function getDeliveryRequestTemplateType(): ?DeliveryRequestTemplateTypeEnum {
        return $this->deliveryRequestTemplateType;
    }

    public function setDeliveryRequestTemplateType(?DeliveryRequestTemplateTypeEnum $deliveryRequestTemplateType): self {
        $this->deliveryRequestTemplateType = $deliveryRequestTemplateType;
        return $this;
    }
}
