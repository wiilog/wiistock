<?php

namespace App\Entity;

use App\Repository\InventoryMissionRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRepository::class)]
class InventoryMission {

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

    public function __construct() {
        $this->refArticles = new ArrayCollection();
        $this->entries = new ArrayCollection();
        $this->articles = new ArrayCollection();
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
     * @return Collection|ReferenceArticle[]
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
     * @return Collection|InventoryEntry[]
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
     * @return Collection|Article[]
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

}
