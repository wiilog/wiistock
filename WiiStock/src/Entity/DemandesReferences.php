<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandesReferencesRepository")
 */
class DemandesReferences
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferencesArticles")
     * @ORM\JoinColumn(nullable=false)
     */
    private $reference;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Demandes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantite;

    public function getId()
    {
        return $this->id;
    }

    public function getReference(): ?ReferencesArticles
    {
        return $this->reference;
    }

    public function setReference(?ReferencesArticles $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getDemande(): ?Demandes
    {
        return $this->demande;
    }

    public function setDemande(?Demandes $demande): self
    {
        $this->demande = $demande;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }
}
