<?php

namespace App\Entity\Inventory;

use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Repository\Inventory\InventoryMissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use Doctrine\ORM\Mapping\OneToMany;

#[ORM\Entity(repositoryClass: InventoryMissionRepository::class)]
class InventoryMission {

    const ARTICLE_TYPE = 'article';
    const LOCATION_TYPE = 'location';

    const INVENTORY_TYPES = [self::ARTICLE_TYPE, self::LOCATION_TYPE];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private ?string $name = null;

    #[ORM\Column(type: 'date')]
    private ?DateTime $startPrevDate = null;

    #[ORM\Column(type: 'date')]
    private ?DateTime $endPrevDate = null;

    #[ORM\ManyToMany(targetEntity: ReferenceArticle::class, inversedBy: 'inventoryMissions')]
    private Collection $refArticles;

    #[ORM\OneToMany(mappedBy: 'mission', targetEntity: InventoryEntry::class)]
    private Collection $entries;

    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'inventoryMissions')]
    private Collection $articles;

    #[ORM\ManyToOne(targetEntity: InventoryMissionRule::class, inversedBy: 'createdMissions')]
    private ?InventoryMissionRule $creator = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $done = null;

    #[ORM\Column(type: 'string')]
    private ?string $type = null;

    #[OneToMany(mappedBy: "inventoryMission", targetEntity: InventoryLocationMission::class, cascade: ['remove'])]
    private Collection $inventoryLocationMissions;

    public function __construct() {
        $this->refArticles = new ArrayCollection();
        $this->entries = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->inventoryLocationMissions = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getStartPrevDate(): ?DateTime {
        return $this->startPrevDate;
    }

    public function setStartPrevDate(DateTime $startPrevDate): self {
        $this->startPrevDate = $startPrevDate;

        return $this;
    }

    public function getEndPrevDate(): ?DateTime {
        return $this->endPrevDate;
    }

    public function setEndPrevDate(DateTime $endPrevDate): self {
        $this->endPrevDate = $endPrevDate;

        return $this;
    }

    /**
     * @return Collection<int, ReferenceArticle>
     */
    public function getRefArticles(): Collection {
        return $this->refArticles;
    }

    public function addRefArticle(ReferenceArticle $refArticle): self {
        if(!$this->refArticles->contains($refArticle)) {
            $this->refArticles[] = $refArticle;
        }

        return $this;
    }

    public function removeRefArticle(ReferenceArticle $refArticle): self {
        if($this->refArticles->contains($refArticle)) {
            $this->refArticles->removeElement($refArticle);
        }

        return $this;
    }

    /**
     * @return Collection<int, InventoryEntry>
     */
    public function getEntries(): Collection {
        return $this->entries;
    }

    public function addEntry(InventoryEntry $entry): self {
        if(!$this->entries->contains($entry)) {
            $this->entries[] = $entry;
            $entry->setMission($this);
        }

        return $this;
    }

    public function removeEntry(InventoryEntry $entry): self {
        if($this->entries->contains($entry)) {
            $this->entries->removeElement($entry);
            // set the owning side to null (unless already changed)
            if($entry->getMission() === $this) {
                $entry->setMission(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection {
        return $this->articles;
    }

    public function addArticle(Article $article): self {
        if(!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->addInventoryMission($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        if($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            $article->removeInventoryMission($this);
        }

        return $this;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getCreator(): ?InventoryMissionRule
    {
        return $this->creator;
    }

    public function setCreator(?InventoryMissionRule $creator): self
    {
        $this->creator = $creator;

        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(string $description): self {
        $this->description = $description;

        return $this;
    }

    public function isDone(): ?bool {
        return $this->done;
    }

    public function setDone(bool $isDone): self {
        $this->done = $isDone;

        return $this;
    }

    public function getType(): ?string {
        return $this->type;
    }

    public function setType(string $type): self {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection<int, InventoryLocationMission>
     */
    public function getInventoryLocationMissions(): Collection {
        return $this->inventoryLocationMissions;
    }

    public function addInventoryLocationMission(InventoryLocationMission $inventoryLocationMission): self {
        if (!$this->inventoryLocationMissions->contains($inventoryLocationMission)) {
            $this->inventoryLocationMissions[] = $inventoryLocationMission;
            $inventoryLocationMission->setInventoryMission($this);
        }

        return $this;
    }

    public function removeInventoryLocationMission(InventoryLocationMission $inventoryLocationMission): self {
        if ($this->inventoryLocationMissions->removeElement($inventoryLocationMission)) {
            if ($inventoryLocationMission->getInventoryMission() === $this) {
                $inventoryLocationMission->setInventoryMission(null);
            }
        }

        return $this;
    }

    public function setInventoryLocationMissions(?iterable $inventoryLocationMissions): self {
        foreach($this->getInventoryLocationMissions()->toArray() as $inventoryLocationMission) {
            $this->removeInventoryLocationMission($inventoryLocationMission);
        }

        $this->inventoryLocationMissions = new ArrayCollection();
        foreach($inventoryLocationMissions ?? [] as $inventoryLocationMission) {
            $this->addInventoryLocationMission($inventoryLocationMission);
        }

        return $this;
    }

}
