<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TypeRepository")
 */
class Type
{
    const LABEL_CSP = 'CSP';
    const LABEL_PDT = 'PDT';
    const LABEL_SILI = 'SILI';
    const LABEL_SILI_EXT = 'SILI-ext';
    const LABEL_SILI_INT = 'SILI-int';
    const LABEL_MOB = 'MOB';
    const LABEL_SLUGCIBLE = 'SLUGCIBLE';
    const LABEL_RECEPTION = 'RECEPTION';
    

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ChampsLibre", mappedBy="type")
     */
    private $champsLibres;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="type")
     */
    private $referenceArticles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="type")
     */
    private $articles;


    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoryType", inversedBy="types")
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="type")
     */
    private $receptions;

    public function __construct()
    {
        $this->champsLibres = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->receptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|ChampsLibre[]
     */
    public function getChampsLibres(): Collection
    {
        return $this->champsLibres;
    }

    public function addChampsLibre(ChampsLibre $champsLibre): self
    {
        if (!$this->champsLibres->contains($champsLibre)) {
            $this->champsLibres[] = $champsLibre;
            $champsLibre->setType($this);
        }

        return $this;
    }

    public function removeChampsLibre(ChampsLibre $champsLibre): self
    {
        if ($this->champsLibres->contains($champsLibre)) {
            $this->champsLibres->removeElement($champsLibre);
            // set the owning side to null (unless already changed)
            if ($champsLibre->getType() === $this) {
                $champsLibre->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferenceArticles(): Collection
    {
        return $this->referenceArticles;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if (!$this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles[] = $referenceArticle;
            $referenceArticle->setType($this);
        }

        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if ($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getType() === $this) {
                $referenceArticle->setType(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?CategoryType
    {
        return $this->category;
    }

    public function setCategory(?CategoryType $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setType($this);
        }

        return $this;
    }
    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getType() === $this) {
                $article->setType(null);
            }
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
            $reception->setType($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getType() === $this) {
                $reception->setType(null);
            }
        }

        return $this;
    }
}
