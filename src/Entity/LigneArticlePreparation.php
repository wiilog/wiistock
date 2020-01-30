<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LigneArticlePreparationRepository")
 */
class LigneArticlePreparation
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantite;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantitePrelevee;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="ligneArticlePreparations")
     * @ORM\JoinColumn(nullable=false)
     */
    private $reference;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="ligneArticlePreparations")
     * @ORM\JoinColumn(nullable=false, name="preparation_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $preparation;

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

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;

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

    public function getReference(): ?ReferenceArticle
    {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        $this->preparation = $preparation;

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
}
