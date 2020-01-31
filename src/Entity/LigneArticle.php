<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LigneArticleRepository")
 */
class LigneArticle
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantitePrelevee;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="ligneArticles")
     */
    private $reference;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Demande", inversedBy="ligneArticle")
     * @ORM\JoinColumn(name="demande_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $demande;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $toSplit;


    public function getId(): ?int
    {
        return $this->id;
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

    public function getReference(): ?ReferenceArticle
    {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getDemande(): ?Demande
    {
        return $this->demande;
    }

    public function setDemande(?Demande $demande): self
    {
        $this->demande = $demande;

        return $this;
    }

    public function getToSplit(): ?bool
    {
        return $this->toSplit;
    }

    public function setToSplit(?bool $toSplit): self
    {
        $this->toSplit = $toSplit;

        return $this;
    }

    public function getQuantitePrelevee(): ?int
    {
        return $this->quantitePrelevee;
    }

    public function setQuantitePrelevee(?int $quantitePrelevee): self
    {
        $this->quantitePrelevee = $quantitePrelevee;

        return $this;
    }

}
