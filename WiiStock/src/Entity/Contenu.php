<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ContenuRepository")
 */
class Contenu
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transferts", inversedBy="contenus")
     */
    private $transfert;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparations", inversedBy="contenus")
     */
    private $preparation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Articles", inversedBy="contenus")
     */
    private $article;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Receptions", inversedBy="contenus")
     */
    private $reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacements", inversedBy="contenus")
     */
    private $emplacement;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zones", inversedBy="contenus")
     */
    private $zone;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais", inversedBy="contenus")
     */
    private $quai;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTransfert(): ?Transfert
    {
        return $this->transfert;
    }

    public function setTransfert(?Transfert $transfert): self
    {
        $this->transfert = $transfert;

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

    public function getArticles(): ?Articles
    {
        return $this->article;
    }

    public function setArticles(?Articles $article): self
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

    public function getEmplacement(): ?Emplacements
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacements $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    public function getZone(): ?Zones
    {
        return $this->zone;
    }

    public function setZone(?Zones $zone): self
    {
        $this->zone = $zone;

        return $this;
    }

    public function getQuai(): ?Quais
    {
        return $this->quai;
    }

    public function setQuai(?Quais $quai): self
    {
        $this->quai = $quai;

        return $this;
    }
}
