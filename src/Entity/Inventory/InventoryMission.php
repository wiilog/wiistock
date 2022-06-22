<?php

namespace App\Entity\Inventory;

use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Repository\Inventory\InventoryMissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRepository::class)]
class InventoryMission {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'date')]
    private $startPrevDate;

    #[ORM\Column(type: 'date')]
    private $endPrevDate;

    #[ORM\ManyToMany(targetEntity: ReferenceArticle::class, inversedBy: 'inventoryMissions')]
    private $refArticles;

    #[ORM\OneToMany(targetEntity: InventoryEntry::class, mappedBy: 'mission')]
    private $entries;

    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'inventoryMissions')]
    private $articles;

    public function __construct() {
        $this->refArticles = new ArrayCollection();
        $this->entries = new ArrayCollection();
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getStartPrevDate(): ?\DateTimeInterface {
        return $this->startPrevDate;
    }

    public function setStartPrevDate(\DateTimeInterface $startPrevDate): self {
        $this->startPrevDate = $startPrevDate;

        return $this;
    }

    public function getEndPrevDate(): ?\DateTimeInterface {
        return $this->endPrevDate;
    }

    public function setEndPrevDate(\DateTimeInterface $endPrevDate): self {
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

}
