<?php

namespace App\Entity\IOT;

use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\IOT\DeliveryRequestTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestTemplateRepository::class)]
class DeliveryRequestTemplate extends RequestTemplate {

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'deliveryRequestTemplates')]
    private ?Emplacement $destination = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToMany(mappedBy: 'deliveryRequestTemplate', targetEntity: RequestTemplateLine::class, cascade: ["remove"])]
    private Collection $lines;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $buttonIcon = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ["default" => true])]
    private ?bool $templateWithReference = null;

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

    public function getButtonIcon(): ?Attachment
    {
        return $this->buttonIcon;
    }

    public function setButtonIcon(?Attachment $buttonIcon): self
    {
        $this->buttonIcon = $buttonIcon;

        return $this;
    }

    public function isTemplateWithReference(): ?bool
    {
        return $this->templateWithReference;
    }

    public function setTemplateWithReference(bool $templateWithReference): self
    {
        $this->templateWithReference = $templateWithReference;

        return $this;
    }

}
