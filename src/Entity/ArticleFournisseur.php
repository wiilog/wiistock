<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticleFournisseurRepository")
 */
class ArticleFournisseur
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="articlesFournisseur")
     * @ORM\JoinColumn(name="reference_article_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private ?ReferenceArticle $referenceArticle = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseur", inversedBy="articlesFournisseur", cascade={"PERSIST"})
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Fournisseur $fournisseur = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $reference = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $label = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $visible = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="articleFournisseur")
     */
    private Collection $articles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceptionReferenceArticle", mappedBy="articleFournisseur")
     */
    private Collection $receptionReferenceArticles;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->receptionReferenceArticles = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReferenceArticle(): ?ReferenceArticle
    {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self
    {
        if($this->referenceArticle && $this->referenceArticle !== $referenceArticle) {
            $this->referenceArticle->removeArticleFournisseur($this);
        }
        $this->referenceArticle = $referenceArticle;
        if($referenceArticle) {
            $referenceArticle->addArticleFournisseur($this);
        }

        return $this;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): self
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
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
            $article->setArticleFournisseur($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getArticleFournisseur() === $this) {
                $article->setArticleFournisseur(null);
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
            $receptionReferenceArticle->setArticleFournisseur($this);
        }

        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if ($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if ($receptionReferenceArticle->getArticleFournisseur() === $this) {
                $receptionReferenceArticle->setArticleFournisseur(null);
            }
        }

        return $this;
    }

    public function isVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(?bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

}
