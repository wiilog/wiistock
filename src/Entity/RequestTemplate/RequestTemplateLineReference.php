<?php

namespace App\Entity\RequestTemplate;

use App\Entity\ReferenceArticle;
use App\Repository\RequestTemplate\RequestTemplateLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestTemplateLineRepository::class)]
class RequestTemplateLineReference extends RequestTemplateLine {
    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'requestTemplateLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReferenceArticle $reference = null;

    public function getReference(): ?ReferenceArticle {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self {
        $this->reference = $reference;

        return $this;
    }
}
