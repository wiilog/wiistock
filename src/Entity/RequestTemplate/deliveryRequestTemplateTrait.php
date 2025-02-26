<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Emplacement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait deliveryRequestTemplateTrait{
    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $destination = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

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
}
