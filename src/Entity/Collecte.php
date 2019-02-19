<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CollecteRepository")
 */
class Collecte
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
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $objet;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="collectes")
     */
    private $pointCollecte;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs", inversedBy="collectes")
     */
    private $demandeur;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Articles", inversedBy="collectes")
     */
    private $articles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statuts", inversedBy="collectes")
     */
    private $Statut;

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getDemandeur(): ?Utilisateurs
    {
        return $this->demandeur;
    }

    public function setDemandeur(?Utilisateurs $demandeur): self
    {
        $this->demandeur = $demandeur;

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

    public function getStatut(): ?Statuts
    {
        return $this->Statut;
    }

    public function setStatut(?Statuts $Statut): self
    {
        $this->Statut = $Statut;

        return $this;
    }

    public function getObjet(): ?string
    {
        return $this->objet;
    }

    public function setObjet(?string $objet): self
    {
        $this->objet = $objet;

        return $this;
    }

    public function getPointCollecte(): ?Emplacement
    {
        return $this->pointCollecte;
    }

    public function setPointCollecte(?Emplacement $pointCollecte): self
    {
        $this->pointCollecte = $pointCollecte;

        return $this;
    }
}
