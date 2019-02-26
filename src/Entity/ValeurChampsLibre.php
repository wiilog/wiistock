<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ValeurChampsLibreRepository")
 */
class ValeurChampsLibre
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $valeur;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\CHampsLibre", inversedBy="valeurChampsLibres")
     */
    private $champsLibre;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ReferenceArticle", inversedBy="valeurChampsLibres")
     */
    private $articleReference;

    public function __construct()
    {
        $this->champsLibre = new ArrayCollection();
        $this->articleReference = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(?string $valeur): self
    {
        $this->valeur = $valeur;

        return $this;
    }

    /**
     * @return Collection|CHampsLibre[]
     */
    public function getChampsLibre(): Collection
    {
        return $this->champsLibre;
    }

    public function addChampsLibre(CHampsLibre $champsLibre): self
    {
        if (!$this->champsLibre->contains($champsLibre)) {
            $this->champsLibre[] = $champsLibre;
        }

        return $this;
    }

    public function removeChampsLibre(CHampsLibre $champsLibre): self
    {
        if ($this->champsLibre->contains($champsLibre)) {
            $this->champsLibre->removeElement($champsLibre);
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getArticleReference(): Collection
    {
        return $this->articleReference;
    }

    public function addArticleReference(ReferenceArticle $articleReference): self
    {
        if (!$this->articleReference->contains($articleReference)) {
            $this->articleReference[] = $articleReference;
        }

        return $this;
    }

    public function removeArticleReference(ReferenceArticle $articleReference): self
    {
        if ($this->articleReference->contains($articleReference)) {
            $this->articleReference->removeElement($articleReference);
        }

        return $this;
    }
}
