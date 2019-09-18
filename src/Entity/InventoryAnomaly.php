<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InventoryAnomalyRepository")
 */
class InventoryAnomaly
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantityStock;

    /**
     * @ORM\Column(type="date")
     */
    private $treatmentDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="anomalies")
     */
    private $operator;

	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\MouvementStock")
	 */
    private $mvtRegulation;

	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\InventoryEntry")
	 */
    private $entry;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantityStock(): ?int
    {
        return $this->quantityStock;
    }

    public function setQuantityStock(int $quantityStock): self
    {
        $this->quantityStock = $quantityStock;

        return $this;
    }

    public function getOperator(): ?Utilisateur
    {
        return $this->operator;
    }

    public function setOperator(?Utilisateur $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    public function getTreatmentDate(): ?\DateTimeInterface
    {
        return $this->treatmentDate;
    }

    public function setTreatmentDate(\DateTimeInterface $treatmentDate): self
    {
        $this->treatmentDate = $treatmentDate;

        return $this;
    }

    public function getMvtRegulation(): ?MouvementStock
    {
        return $this->mvtRegulation;
    }

    public function setMvtRegulation(?MouvementStock $mvtRegulation): self
    {
        $this->mvtRegulation = $mvtRegulation;

        return $this;
    }

    public function getEntry(): ?InventoryEntry
    {
        return $this->entry;
    }

    public function setEntry(?InventoryEntry $entry): self
    {
        $this->entry = $entry;

        return $this;
    }
}
