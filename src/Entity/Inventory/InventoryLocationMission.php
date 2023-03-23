<?php

namespace App\Entity\Inventory;

use App\Entity\Emplacement;
use App\Entity\Utilisateur;
use App\Entity\Article;
use App\Repository\Inventory\InventoryMissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRepository::class)]
class InventoryLocationMission {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryMission::class, inversedBy: 'inventoryLocationMissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InventoryMission $inventoryMission = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'inventoryLocationMissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Emplacement $location = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $done = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $operator = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $scannedAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $percentage = null;

    #[ORM\ManyToMany(targetEntity: Article::class)]
    private Collection $articles;

    public function __construct() {

    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getInventoryMission(): ?InventoryMission {
        return $this->inventoryMission;
    }

    public function setInventoryMission(?InventoryMission $inventoryMission): self {
        if($this->inventoryMission && $this->inventoryMission !== $inventoryMission) {
            $this->inventoryMission->removeInventoryLocationMission($this);
        }
        $this->inventoryMission = $inventoryMission;
        $inventoryMission?->addInventoryLocationMission($this);

        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self {
        if($this->location && $this->location !== $location) {
            $this->location->removeInventoryLocationMission($this);
        }
        $this->location = $location;
        $location?->addInventoryLocationMission($this);

        return $this;
    }

    public function isDone(): ?bool {
        return $this->done;
    }

    public function setDone(bool $isDone): self {
        $this->done = $isDone;

        return $this;
    }

    public function getOperator(): ?Utilisateur {
        return $this->operator;
    }

    public function setOperator(Utilisateur $operator): self {
        $this->operator = $operator;

        return $this;
    }

    public function getScannedAt(): ?DateTime {
        return $this->scannedAt;
    }

    public function setScannedAt(DateTime $scannedAt): self {
        $this->scannedAt = $scannedAt;

        return $this;
    }

    public function getPercentage(): ?int {
        return $this->percentage;
    }

    public function setPercentage(int $percentage): self {
        $this->percentage = $percentage;

        return $this;
    }

    public function getArticles(): Collection {
        return $this->articles;
    }

    public function addArticle(Article $article): self {

        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        $this->articles->removeElement($article);

        return $this;
    }

    /**
     * @param Article[] $articles
     */
    public function setArticles(array $articles): self {
        foreach($this->getArticles()->toArray() as $article) {
            $this->removeArticle($article);
        }

        $this->articles = new ArrayCollection();
        foreach ($articles as $article) {
            $this->addArticle($article);
        }

        return $this;
    }

}
