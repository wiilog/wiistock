<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PreparationRepository")
 */
class Preparation
{
    const CATEGORIE = 'preparation';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_EN_COURS_DE_PREPARATION = 'en cours de préparation';
    const STATUT_PREPARE = 'préparé';
	const STATUT_INCOMPLETE = 'partiellement préparé';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     */
    private $numero;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Demande", inversedBy="preparations")
     */
    private $demande;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="preparations")
     */
    private $statut;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="preparations")
     */
    private $utilisateur;

    /**
     * @var Livraison|null
     * @ORM\OneToOne(targetEntity="App\Entity\Livraison", mappedBy="preparation")
     */
    private $livraison;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="preparationOrder")
	 */
	private $mouvements;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="preparation")
     */
    private $articles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LigneArticlePreparation", mappedBy="preparation")
     */
    private $ligneArticlePreparations;

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class)
     */
    private $endLocation;


    public function __construct()
    {
        $this->mouvements = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->ligneArticlePreparations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(?DateTime $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    /**
     * @return Demande|null
     */
    public function getDemande(): ?Demande {
        return $this->demande;
    }

    public function setDemande(?Demande $demande): self {
        $this->demande = $demande;
        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    /**
     * @return Livraison|null
     */
    public function getLivraison(): ?Livraison {
        return $this->livraison;
    }

    public function setLivraison(?Livraison $livraison): self
    {
        if (isset($this->livraison) && ($this->livraison !== $livraison)) {
            $this->livraison->setPreparation(null);
        }

        $this->livraison = $livraison;

        if (isset($this->livraison)) {
            $this->livraison->setPreparation($this);
        }

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection
    {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self
    {
        if (!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setPreparationOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getPreparationOrder() === $this) {
                $mouvement->setPreparationOrder(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->getDemande() ? $this->getDemande()->getCommentaire() : "";
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
            $article->setPreparation($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getPreparation() === $this) {
                $article->setPreparation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|LigneArticlePreparation[]
     */
    public function getLigneArticlePreparations(): Collection
    {
        return $this->ligneArticlePreparations;
    }

    public function addLigneArticlePreparation(LigneArticlePreparation $ligneArticlePreparation): self
    {
        if (!$this->ligneArticlePreparations->contains($ligneArticlePreparation)) {
            $this->ligneArticlePreparations[] = $ligneArticlePreparation;
            $ligneArticlePreparation->setPreparation($this);
        }

        return $this;
    }

    public function removeLigneArticlePreparation(LigneArticlePreparation $ligneArticlePreparation): self
    {
        if ($this->ligneArticlePreparations->contains($ligneArticlePreparation)) {
            $this->ligneArticlePreparations->removeElement($ligneArticlePreparation);
            // set the owning side to null (unless already changed)
            if ($ligneArticlePreparation->getPreparation() === $this) {
                $ligneArticlePreparation->setPreparation(null);
            }
        }

        return $this;
    }

    public function getEndLocation(): ?Emplacement {
        return $this->endLocation;
    }

    public function setEndLocation(?Emplacement $endLocation): self {
        $this->endLocation = $endLocation;
        return $this;
    }
}
