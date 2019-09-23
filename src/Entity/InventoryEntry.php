<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InventoryEntryRepository")
 */
class InventoryEntry
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="inventoryEntries")
     */
    private $refArticle;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", inversedBy="inventoryEntries")
     */
    private $article;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="inventoryEntries")
     * @ORM\JoinColumn(nullable=false)
     */
    private $operator;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantity;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement")
     * @ORM\JoinColumn(nullable=false)
     */
    private $location;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\InventoryMission", inversedBy="entries")
	 */
    private $mission;

    /**
	 * @ORM\OneToOne(targetEntity="App\Entity\MouvementStock", mappedBy="entry")
	 */
    private $mouvementStock;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getRefArticle(): ?ReferenceArticle
    {
        return $this->refArticle;
    }

    public function setRefArticle(?ReferenceArticle $refArticle): self
    {
        $this->refArticle = $refArticle;

        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getLocation(): ?Emplacement
    {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getMission(): ?InventoryMission
    {
        return $this->mission;
    }

    public function setMission(?InventoryMission $mission): self
    {
        $this->mission = $mission;

        return $this;
    }

    public function getMouvementStock(): ?MouvementStock
    {
        return $this->mouvementStock;
    }

    public function setMouvementStock(?MouvementStock $mouvementStock): self
    {
        $this->mouvementStock = $mouvementStock;

        return $this;
    }

}
