<?php

namespace App\Entity\Inventory;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Repository\Inventory\InventoryEntryRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;

#[ORM\Entity(repositoryClass: InventoryEntryRepository::class)]
class InventoryEntry {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    private ?DateTime $date = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'inventoryEntries')]
    private ?ReferenceArticle $refArticle = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'inventoryEntries')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'inventoryEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $operator = null;

    #[ORM\Column(type: 'integer')]
    private ?int $quantity = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: InventoryMission::class, inversedBy: 'entries')]
    private ?InventoryMission $mission = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $anomaly = false;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(DateTime $date): self {
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

    #[ArrayShape([
        'operator' => "null|string",
        'location' => "null|string",
        'date' => "null|string",
        'quantity' => "null|int"
    ])]
    public function serialize(): array {
        return [
            'operator' => FormatHelper::user($this->getOperator()),
            'location' => FormatHelper::location($this->getLocation()),
            'date' => FormatHelper::date($this->getDate()),
            'quantity' => $this->getQuantity(),
        ];
    }

}
