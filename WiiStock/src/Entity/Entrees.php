<?php

namespace App\Entity;

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
     * @ORM\Column(type="integer")
     */
    private $quantite;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais")
     * @ORM\JoinColumn(nullable=false)
     */
    private $quai_entree;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date_entree;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Articles")
     * @ORM\JoinColumn(nullable=false)
     */
    private $article;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Receptions")
     */
    private $reception;

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

    public function setDateEntree(\DateTimeInterface $date_entree): self
    {
        $this->date_entree = $date_entree;

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

    public function getReception(): ?Receptions
    {
        return $this->reception;
    }

    public function setReception(?Receptions $reception): self
    {
        $this->reception = $reception;

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
