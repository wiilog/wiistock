<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceptionRepository")
 */
class Reception
{
    const CATEGORIE = 'reception';

    const STATUT_EN_ATTENTE = 'en attente de réception';
    const STATUT_RECEPTION_PARTIELLE = 'réception partielle';
    const STATUT_RECEPTION_TOTALE = 'réception totale';
    const STATUT_ANOMALIE = 'anomalie';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseur", inversedBy="receptions")
     */
    private $fournisseur;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $numeroReception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="receptions")
     */
    private $utilisateur;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="receptions")
     */
    private $Statut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateAttendu;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateReception;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $reference;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceptionReferenceArticle", mappedBy="reception")
     */
    private $receptionReferenceArticles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="reception")
     */
    private $articles;
    /** 
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="receptions")
     */
    private $type;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampsLibre", inversedBy="receptions")
     */
    private $valeurChampsLibre;


    public function __construct()
    {
        $this->receptionReferenceArticles = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->valeurChampsLibre = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }


    public function __toString()
    {
        return $this->commentaire;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getNumeroReception(): ?string
    {
        return $this->numeroReception;
    }

    public function setNumeroReception(?string $numeroReception): self
    {
        $this->numeroReception = $numeroReception;

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

    public function getStatut(): ?Statut
    {
        return $this->Statut;
    }

    public function setStatut(?Statut $Statut): self
    {
        $this->Statut = $Statut;

        return $this;
    }

    public function getDateAttendu(): ?\DateTimeInterface
    {
        return $this->dateAttendu;
    }

    public function setDateAttendu(?\DateTimeInterface $dateAttendu): self
    {
        $this->dateAttendu = $dateAttendu;

        return $this;
    }

    public function getDateReception(): ?\DateTimeInterface
    {
        return $this->dateReception;
    }

    public function setDateReception(?\DateTimeInterface $dateReception): self
    {
        $this->dateReception = $dateReception;

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
            $receptionReferenceArticle->setReception($this);
        }

        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if ($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if ($receptionReferenceArticle->getReception() === $this) {
                $receptionReferenceArticle->setReception(null);
            }
        }

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
            $article->setReception($this);
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

    /**
     * @return Collection|valeurChampsLibre[]
     */
    public function getValeurChampsLibre(): Collection
    {
        return $this->valeurChampsLibre;
    }

    public function addValeurChampsLibre(valeurChampsLibre $valeurChampsLibre): self
    {
        if (!$this->valeurChampsLibre->contains($valeurChampsLibre)) {
            $this->valeurChampsLibre[] = $valeurChampsLibre;
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getReception() === $this) {
                $article->setReception(null);
            }
        }
        return $this;
    }
    public function removeValeurChampsLibre(valeurChampsLibre $valeurChampsLibre): self
    {
        if ($this->valeurChampsLibre->contains($valeurChampsLibre)) {
            $this->valeurChampsLibre->removeElement($valeurChampsLibre);
        }

        return $this;
    }
}
