<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceptionsRepository")
 */
class Receptions
{
    const CATEGORIE = 'reception';
    const STATUT_EN_COURS = 'en cours de réception';
    const TERMINE = 'terminée';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseurs", inversedBy="receptions")
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs", inversedBy="receptions")
     */
    private $utilisateur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Articles", mappedBy="reception")
     */
    private $articles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statuts", inversedBy="receptions")
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
    

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFournisseur(): ?Fournisseurs
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseurs $fournisseur): self
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
        return $this->id;
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

    public function getUtilisateur(): ?Utilisateurs
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateurs $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    /**
     * @return Collection|Articles[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Articles $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setReception($this);
        }

        return $this;
    }

    public function removeArticle(Articles $article): self
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

    public function getStatut(): ?Statuts
    {
        return $this->Statut;
    }

    public function setStatut(?Statuts $Statut): self
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
}
