<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandesMisesEnStocksRepository")
 */
class DemandesMisesEnStocks
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date_mise_en_stock;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacements")
     * @ORM\JoinColumn(nullable=false)
     */
    private $emplacement;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Demandes", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    public function getId()
    {
        return $this->id;
    }

    public function getDateMiseEnStock(): ?\DateTimeInterface
    {
        return $this->date_mise_en_stock;
    }

    public function setDateMiseEnStock(\DateTimeInterface $date_mise_en_stock): self
    {
        $this->date_mise_en_stock = $date_mise_en_stock;

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

    public function getDemande(): ?Demandes
    {
        return $this->demande;
    }

    public function setDemande(Demandes $demande): self
    {
        $this->demande = $demande;

        return $this;
    }
}
