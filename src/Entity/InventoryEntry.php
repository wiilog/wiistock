<?php

namespace App\Entity;

use App\Helper\FormatHelper;
use App\Repository\InventoryEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryEntryRepository::class)]
class InventoryEntry {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'date')]
    private $date;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'inventoryEntries')]
    private $refArticle;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'inventoryEntries')]
    private $article;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'inventoryEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private $operator;

    #[ORM\Column(type: 'integer')]
    private $quantity;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $location;

    #[ORM\ManyToOne(targetEntity: InventoryMission::class, inversedBy: 'entries')]
    private $mission;

    #[ORM\Column(type: 'boolean')]
    private $anomaly = false;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self {
        $this->date = $date;

        return $this;
    }

    public function getRefArticle(): ?ReferenceArticle {
        return $this->refArticle;
    }

    public function setRefArticle(?ReferenceArticle $refArticle): self {
        $this->refArticle = $refArticle;

        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        $this->article = $article;

        return $this;
    }

    public function getOperator(): ?Utilisateur {
        return $this->operator;
    }

    public function setOperator(?Utilisateur $operator): self {
        $this->operator = $operator;

        return $this;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self {
        $this->quantity = $quantity;

        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self {
        $this->location = $location;

        return $this;
    }

    public function getMission(): ?InventoryMission {
        return $this->mission;
    }

    public function setMission(?InventoryMission $mission): self {
        $this->mission = $mission;

        return $this;
    }

    public function getAnomaly(): ?bool {
        return $this->anomaly;
    }

    public function setAnomaly(bool $anomaly): self {
        $this->anomaly = $anomaly;

        return $this;
    }

    public function serialize() {
        return [
            'operator' => FormatHelper::user($this->getOperator()),
            'location' => FormatHelper::location($this->getLocation()),
            'date' => FormatHelper::date($this->getDate()),
            'quantity' => $this->getQuantity(),
        ];
    }

}
