<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EntreesRepository")
 */
class Entrees
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $statut;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais")
     */
    private $quai_entree;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_entree;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Historiques", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $historique;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Articles", mappedBy="entrees", cascade={"persist"})
     */
    private $articles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Ordres", inversedBy="entrees")
     */
    private $ordres;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Receptions", inversedBy="entrees")
     */
    private $reception;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $quantite;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getQuaiEntree(): ?Quais
    {
        return $this->quai_entree;
    }

    public function setQuaiEntree(?Quais $quai_entree): self
    {
        $this->quai_entree = $quai_entree;

        return $this;
    }

    public function getDateEntree(): ?\DateTimeInterface
    {
        return $this->date_entree;
    }

    public function setDateEntree(?\DateTimeInterface $date_entree): self
    {
        $this->date_entree = $date_entree;

        return $this;
    }

    public function getHistorique(): ?Historiques
    {
        return $this->historique;
    }

    public function setHistorique(?Historiques $historique): self
    {
        $this->historique = $historique;

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
            $article->addEntree($this);
        }

        return $this;
    }

    public function removeArticle(Articles $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            $article->removeEntree($this);
        }

        return $this;
    }

    public function getOrdres(): ?Ordres
    {
        return $this->ordres;
    }

    public function setOrdres(?Ordres $ordres): self
    {
        $this->ordres = $ordres;

        return $this;
    }

    public function getReception(): ?Receptions
    {
        return $this->reception;
    }

    public function setReception(?Receptions $reception): self
    {
        $this->reception = $reception;

        return $this;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(?string $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }
}
