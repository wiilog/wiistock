<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReferenceArticleRepository")
 */
class ReferenceArticle
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
    private $libelle;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $photo_article;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $reference;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $custom;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="refArticle")
     */
    private $articles;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteDisponible;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Alerte", mappedBy="AlerteRefArticle")
     */
    private $RefArticleAlerte;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteReservee;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteStock;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LigneArticle", mappedBy="reference")
     */
    private $ligneArticles;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampsLibre", mappedBy="articleReference")
     */
    private $valeurChampsLibres;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="referenceArticles")
     */
    private $type;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->RefArticleAlerte = new ArrayCollection();
        $this->ligneArticles = new ArrayCollection();
        $this->valeurChampsLibres = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getPhotoArticle(): ?string
    {
        return $this->photo_article;
    }

    public function setPhotoArticle(?string $photo_article): self
    {
        $this->photo_article = $photo_article;

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

    public function getCustom(): ?array
    {
        return $this->custom;
    }

    public function setCustom(?array $custom): self
    {
        $this->custom = $custom;

        return $this;
    }

    

    public function __toString()
    {
        return $this->reference;
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
            $article->setRefArticle($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getRefArticle() === $this) {
                $article->setRefArticle(null);
            }
        }

        return $this;
    }

    public function getQuantiteDisponible(): ?int
    {
        return $this->QuantiteDisponible;
    }

    public function setQuantiteDisponible(?int $quantiteDisponible): self
    {
        $this->QuantiteDisponible = $quantiteDisponible;

        return $this;
    }

    /**
     * @return Collection|Alerte[]
     */
    public function getRefArticleAlerte(): Collection
    {
        return $this->RefArticleAlerte;
    }

    public function addRefArticleAlerte(Alerte $refArticleAlerte): self
    {
        if (!$this->RefArticleAlerte->contains($refArticleAlerte)) {
            $this->RefArticleAlerte[] = $refArticleAlerte;
            $refArticleAlerte->setAlerteRefArticle($this);
        }

        return $this;
    }

    public function removeRefArticleAlerte(Alerte $refArticleAlerte): self
    {
        if ($this->RefArticleAlerte->contains($refArticleAlerte)) {
            $this->RefArticleAlerte->removeElement($refArticleAlerte);
            // set the owning side to null (unless already changed)
            if ($refArticleAlerte->getAlerteRefArticle() === $this) {
                $refArticleAlerte->setAlerteRefArticle(null);
            }
        }

        return $this;
    }

    public function getQuantiteReservee(): ?int
    {
        return $this->quantiteReservee;
    }

    public function setQuantiteReservee(?int $quantiteReservee): self
    {
        $this->quantiteReservee = $quantiteReservee;

        return $this;
    }

    public function getQuantiteStock(): ?int
    {
        return $this->quantiteStock;
    }

    public function setQuantiteStock(?int $quantiteStock): self
    {
        $this->quantiteStock = $quantiteStock;

        return $this;
    }

    /**
     * @return Collection|LigneArticle[]
     */
    public function getLigneArticles(): Collection
    {
        return $this->ligneArticles;
    }

    public function addLigneArticle(LigneArticle $ligneArticle): self
    {
        if (!$this->ligneArticles->contains($ligneArticle)) {
            $this->ligneArticles[] = $ligneArticle;
            $ligneArticle->setReference($this);
        }

        return $this;
    }

    public function removeLigneArticle(LigneArticle $ligneArticle): self
    {
        if ($this->ligneArticles->contains($ligneArticle)) {
            $this->ligneArticles->removeElement($ligneArticle);
            // set the owning side to null (unless already changed)
            if ($ligneArticle->getReference() === $this) {
                $ligneArticle->setReference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ValeurChampsLibre[]
     */
    public function getValeurChampsLibres(): Collection
    {
        return $this->valeurChampsLibres;
    }

    public function addValeurChampsLibre(ValeurChampsLibre $valeurChampsLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres[] = $valeurChampsLibre;
            $valeurChampsLibre->addArticleReference($this);
        }

        return $this;
    }

    public function removeValeurChampsLibre(ValeurChampsLibre $valeurChampsLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampsLibre);
            $valeurChampsLibre->removeArticleReference($this);
        }

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }
}
