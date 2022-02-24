<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\CollecteReferenceRepository')]
class CollecteReference {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Collecte::class, inversedBy: 'collecteReferences')]
    private $collecte;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $quantite;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'collecteReferences')]
    private $referenceArticle;

    public function getId(): ?int {
        return $this->id;
    }

    public function getCollecte(): ?Collecte {
        return $this->collecte;
    }

    public function setCollecte(?Collecte $collecte): self {
        $this->collecte = $collecte;

        return $this;
    }

    public function getQuantite(): ?int {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self {
        $this->quantite = $quantite;

        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self {
        $this->referenceArticle = $referenceArticle;

        return $this;
    }

}
