<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FournisseurRepository")
 */
class Fournisseur
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $codeReference;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="fournisseur")
     */
    private $receptions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ArticleFournisseur", mappedBy="fournisseur")
     */
    private $articlesFournisseur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceptionReferenceArticle", mappedBy="fournisseur")
     */
    private $receptionReferenceArticles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="fournisseur")
     */
    private $arrivages;

    public function __construct()
    {
        $this->receptions = new ArrayCollection();
        $this->articlesFournisseur = new ArrayCollection();
        $this->receptionReferenceArticles = new ArrayCollection();
        $this->arrivages = new ArrayCollection();
    }

    public function getId() : ? int
    {
        return $this->id;
    }

    public function getCodeReference() : ? string
    {
        return $this->codeReference;
    }

    public function setCodeReference(? string $codeReference) : self
    {
        $this->codeReference = $codeReference;

        return $this;
    }

    public function getNom() : ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom) : self
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions() : Collection
    {
        return $this->receptions;
    }

    public function addReceptions(Reception $reception) : self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setFournisseur($this);
        }

        return $this;
    }

    public function removeReceptions(Reception $reception) : self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getFournisseur() === $this) {
                $reception->setFournisseur(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->nom;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setFournisseur($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getFournisseur() === $this) {
                $reception->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ArticleFournisseur[]
     */
    public function getArticlesFournisseur(): Collection
    {
        return $this->articlesFournisseur;
    }

    public function addArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if (!$this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur[] = $articlesFournisseur;
            $articlesFournisseur->setFournisseur($this);
        }

        return $this;
    }

    public function removeArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if ($this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur->removeElement($articlesFournisseur);
            // set the owning side to null (unless already changed)
            if ($articlesFournisseur->getFournisseur() === $this) {
                $articlesFournisseur->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReceptionReferenceArticle[]
     */
    public function getReceptionReferenceArticles(): Collection
    {
        return $this->receptionReferenceArticles;
    }

    public function addReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if (!$this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles[] = $receptionReferenceArticle;
            $receptionReferenceArticle->setFournisseur($this);
        }

        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if ($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if ($receptionReferenceArticle->getFournisseur() === $this) {
                $receptionReferenceArticle->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivages(): Collection
    {
        return $this->arrivages;
    }

    public function addArrivage(Arrivage $arrivage): self
    {
        if (!$this->arrivages->contains($arrivage)) {
            $this->arrivages[] = $arrivage;
            $arrivage->setFournisseur($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self
    {
        if ($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if ($arrivage->getFournisseur() === $this) {
                $arrivage->setFournisseur(null);
            }
        }

        return $this;
    }
}
