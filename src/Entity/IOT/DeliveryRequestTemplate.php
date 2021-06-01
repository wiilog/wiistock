<?php

namespace App\Entity\IOT;

use App\Entity\Emplacement;
use App\Repository\IOT\DeliveryRequestTemplateRepository;
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

}
