<?php

namespace App\Entity\IOT;

use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\IOT\DeliveryRequestTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryRequestTemplateRepository::class)
 */
class DeliveryRequestTemplate extends RequestTemplate {

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class, inversedBy="deliveryRequestTemplates")
     */
    private ?Emplacement $destination = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    /**
     * @ORM\OneToMany(targetEntity=RequestTemplateLine::class, mappedBy="deliveryRequestTemplate")
     */
    private Collection $lines;

    public function __construct()
    {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    public function getDestination(): ?Emplacement {
        return $this->destination;
    }

    public function setDestination(?Emplacement $destination): self {
        if ($this->destination && $this->destination !== $destination) {
            $this->destination->removeDeliveryRequestTemplate($this);
        }
        $this->destination = $destination;
        if ($destination) {
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
     * @return Collection|ReferenceArticle[]
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(ReferenceArticle $ref): self
    {
        if (!$this->lines->contains($ref)) {
            $this->lines[] = $ref;
        }

        return $this;
    }

    public function removeLine(ReferenceArticle $ref): self
    {
        $this->lines->removeElement($ref);

        return $this;
    }

}
