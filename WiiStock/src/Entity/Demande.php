<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandeRepository")
 */
class Demande
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
    private $numero;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="demandes")
     */
    private $destination;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs", inversedBy="demandes")
     */
    private $utilisateur;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Articles", inversedBy="demandes")
     */
    private $articles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="demandes")
     */
    private $preparation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Livraison", inversedBy="demande")
     */
    private $livraison;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statuts", inversedBy="demandes")
     */
    private $Statut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $DateAttendu;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $LigneArticle;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDestination(): ?Emplacement
    {
        return $this->destination;
    }

    public function setDestination(?Emplacement $destination): self
    {
        $this->destination = $destination;

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

    public function __toString()
    {
        return $this->statut;
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
        }

        return $this;
    }

    public function removeArticle(Articles $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
        }

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        $this->preparation = $preparation;

        return $this;
    }

    public function getLivraison(): ?Livraison
    {
        return $this->livraison;
    }

    public function setLivraison(?Livraison $livraison): self
    {
        $this->livraison = $livraison;

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
        return $this->DateAttendu;
    }

    public function setDateAttendu(?\DateTimeInterface $DateAttendu): self
    {
        $this->DateAttendu = $DateAttendu;

        return $this;
    }

    public function getLigneArticle()
    {
        return $this->LigneArticle;
    }

    public function addLigneArticle($LigneArticle): self
    {
        $this->LigneArticle[] = $LigneArticle;

        return $this;
    }

    public function setLigneArticle($LigneArticle): self
    {
        $this->LigneArticle = $LigneArticle;

        return $this;
    }
}
