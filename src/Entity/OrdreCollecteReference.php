<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OrdreCollecteReferenceRepository")
 */
class OrdreCollecteReference
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\OrdreCollecte", inversedBy="ordreCollecteReferences")
     */
    private $ordreCollecte;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle")
     */
    private $referenceArticle;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrdreCollecte(): ?OrdreCollecte
    {
        return $this->ordreCollecte;
    }

    public function setOrdreCollecte(?OrdreCollecte $ordreCollecte): self
    {
        $this->ordreCollecte = $ordreCollecte;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle
    {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self
    {
        $this->referenceArticle = $referenceArticle;

        return $this;
    }
}
