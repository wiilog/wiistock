<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceptionReferenceArticleRepository")
 */
class ReceptionReferenceArticle
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="receptionReferenceArticles")
     */
    private $reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="receptionReferenceArticles")
     */
    private $referenceArticle;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteAR;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseur", inversedBy="receptionReferenceArticles")
     */
    private $fournisseur;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $anomalie;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commande;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ArticleFournisseur", inversedBy="receptionReferenceArticles")
     */
    private $articleFournisseur;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="receptionReferenceArticle")
	 */
    private $articles;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $emergencyTriggered;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $emergencyComment;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementTraca", mappedBy="receptionReferenceArticle")
     */
    private $mouvementsTraca;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->mouvementsTraca = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReception(): ?Reception
    {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self
    {
        $this->reception = $reception;

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

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getQuantiteAR(): ?int
    {
        return $this->quantiteAR;
    }

    public function setQuantiteAR(?int $quantiteAR): self
    {
        $this->quantiteAR = $quantiteAR;

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

    public function getAnomalie(): ?bool
    {
        return $this->anomalie;
    }

    public function setAnomalie(?bool $anomalie): self
    {
        $this->anomalie = $anomalie;

        return $this;
    }

    public function getCommande(): ?string
    {
        return $this->commande;
    }

    public function setCommande(?string $commande): self
    {
        $this->commande = $commande;

        return $this;
    }

    public function getArticleFournisseur(): ?ArticleFournisseur
    {
        return $this->articleFournisseur;
    }

    public function setArticleFournisseur(?ArticleFournisseur $articleFournisseur): self
    {
        $this->articleFournisseur = $articleFournisseur;

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
            $article->setReceptionReferenceArticle($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getReceptionReferenceArticle() === $this) {
                $article->setReceptionReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getEmergencyTriggered(): ?bool
    {
        return $this->emergencyTriggered;
    }

    public function setEmergencyTriggered(?bool $emergencyTriggered): self
    {
        $this->emergencyTriggered = $emergencyTriggered;

        return $this;
    }

    public function getEmergencyComment(): ?string
    {
        return $this->emergencyComment;
    }

    public function setEmergencyComment(?string $emergencyComment): self
    {
        $this->emergencyComment = $emergencyComment;

        return $this;
    }

    /**
     * @return Collection|MouvementTraca[]
     */
    public function getMouvementsTraca(): Collection
    {
        return $this->mouvementsTraca;
    }

    public function addMouvementsTraca(MouvementTraca $mouvementsTraca): self
    {
        if (!$this->mouvementsTraca->contains($mouvementsTraca)) {
            $this->mouvementsTraca[] = $mouvementsTraca;
            $mouvementsTraca->setReceptionReferenceArticle($this);
        }

        return $this;
    }

    public function removeMouvementsTraca(MouvementTraca $mouvementsTraca): self
    {
        if ($this->mouvementsTraca->contains($mouvementsTraca)) {
            $this->mouvementsTraca->removeElement($mouvementsTraca);
            // set the owning side to null (unless already changed)
            if ($mouvementsTraca->getReceptionReferenceArticle() === $this) {
                $mouvementsTraca->setReceptionReferenceArticle(null);
            }
        }

        return $this;
    }
}
