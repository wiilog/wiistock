<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SortiesRepository")
 */
class Sorties
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
     * @ORM\Column(type="integer")
     */
    private $quantite;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais")
     * @ORM\JoinColumn(nullable=false)
     */
    private $quai_sortie;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Articles")
     * @ORM\JoinColumn(nullable=false)
     */
    private $article;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparations")
     */
    private $preparation;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Historiques", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $historique;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Ordres", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $ordre;

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

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getQuaiSortie(): ?Quais
    {
        return $this->quai_sortie;
    }

    public function setQuaiSortie(?Quais $quai_sortie): self
    {
        $this->quai_sortie = $quai_sortie;

        return $this;
    }

    public function getArticle(): ?Articles
    {
        return $this->article;
    }

    public function setArticle(?Articles $article): self
    {
        $this->article = $article;

        return $this;
    }

    public function getPreparation(): ?Preparations
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparations $preparation): self
    {
        $this->preparation = $preparation;

        return $this;
    }

    public function getHistorique(): ?Historiques
    {
        return $this->historique;
    }

    public function setHistorique(Historiques $historique): self
    {
        $this->historique = $historique;

        return $this;
    }

    public function getOrdre(): ?Ordres
    {
        return $this->ordre;
    }

    public function setOrdre(Ordres $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }
}
