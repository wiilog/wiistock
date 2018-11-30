<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticlesRepository")
 */
class Articles
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
    private $etat;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacements")
     */
    private $emplacement;

   
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferencesArticles")
     */
    private $reference;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $photo;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zones", inversedBy="articles")
     */
    private $zone;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais", inversedBy="articles")
     */
    private $quai;

    public function __construct()
    {
        $this->entrees = new ArrayCollection();
        $this->sorties = new ArrayCollection();
        $this->transferts = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;

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

    public function getReference(): ?ReferencesArticles
    {
        return $this->reference;
    }

    public function setReference(?ReferencesArticles $reference): self
    {
        $this->reference = $reference;

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

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;

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
