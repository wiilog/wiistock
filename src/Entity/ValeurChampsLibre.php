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
     * @ORM\ManyToMany(targetEntity="App\Entity\ReferenceArticle", inversedBy="valeurChampsLibres")
     */
    private $articleReference;
    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Article", inversedBy="valeurChampsLibres")
     */
    private $article;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ChampsLibre", inversedBy="valeurChampsLibres")
     * @ORM\JoinColumn(name="champ_libre_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $champLibre;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Reception", mappedBy="valeurChampsLibre")
     */
    private $receptions;

    public function __construct()
    {
        $this->champLibre = new ArrayCollection();
        $this->articleReference = new ArrayCollection();
        $this->article = new ArrayCollection();
        $this->receptions = new ArrayCollection();
    }


    public function __toString()
    {
        return $this->valeur;
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

    public function getChampLibre(): ?ChampsLibre
    {
        return $this->champLibre;
    }

    public function setChampLibre(?ChampsLibre $champLibre): self
    {
        $this->champLibre = $champLibre;

        return $this;
    }


    /**
    * @return Collection|Article[]
    */
    public function getArticle(): Collection
    {
        return $this->article;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->article->contains($article)) {
            $this->article[] = $article;
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->article->contains($article)) {
            $this->article->removeElement($article);
        }

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions(): Collection
    {
        return $this->receptions;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->addValeurChampsLibre($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            $reception->removeValeurChampsLibre($this);
        }

        return $this;
    }
}