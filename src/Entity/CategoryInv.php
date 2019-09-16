<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CategoryInvRepository")
 */
class CategoryInv
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $label;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\FrequencyInv", inversedBy="categoryInvs")
     */
    private $frequency;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="category")
     */
    private $article;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="category")
     */
    private $refArticle;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\HistoryCategory", mappedBy="categoryBefore")
     */
    private $historyCategories;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\HistoryCategory", mappedBy="categoryAfter")
     */
    private $historyCategoriesAfter;

    public function __construct()
    {
        $this->frequency = new ArrayCollection();
        $this->historyCategories = new ArrayCollection();
        $this->article = new ArrayCollection();
        $this->refArticle = new ArrayCollection();
        $this->historyCategoriesAfter = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|FrequencyInv[]
     */
    public function getFrequency(): Collection
    {
        return $this->frequency;
    }

    public function addFrequency(FrequencyInv $frequency): self
    {
        if (!$this->frequency->contains($frequency)) {
            $this->frequency[] = $frequency;
        }

        return $this;
    }

    public function removeFrequency(FrequencyInv $frequency): self
    {
        if ($this->frequency->contains($frequency)) {
            $this->frequency->removeElement($frequency);
        }

        return $this;
    }

    /**
     * @return Collection|HistoryCategory[]
     */
    public function getHistoryCategories(): Collection
    {
        return $this->historyCategories;
    }

    public function addHistoryCategory(HistoryCategory $historyCategory): self
    {
        if (!$this->historyCategories->contains($historyCategory)) {
            $this->historyCategories[] = $historyCategory;
            $historyCategory->setCategoryBefore($this);
        }

        return $this;
    }

    public function removeHistoryCategory(HistoryCategory $historyCategory): self
    {
        if ($this->historyCategories->contains($historyCategory)) {
            $this->historyCategories->removeElement($historyCategory);
            // set the owning side to null (unless already changed)
            if ($historyCategory->getCategoryBefore() === $this) {
                $historyCategory->setCategoryBefore(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticle(): Collection
    {
        return $this->article;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->article->contains($article)) {
            $this->article[] = $article;
            $article->setCategory($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->article->contains($article)) {
            $this->article->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getCategory() === $this) {
                $article->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getRefArticle(): Collection
    {
        return $this->refArticle;
    }

    public function addRefArticle(ReferenceArticle $refArticle): self
    {
        if (!$this->refArticle->contains($refArticle)) {
            $this->refArticle[] = $refArticle;
            $refArticle->setCategory($this);
        }

        return $this;
    }

    public function removeRefArticle(ReferenceArticle $refArticle): self
    {
        if ($this->refArticle->contains($refArticle)) {
            $this->refArticle->removeElement($refArticle);
            // set the owning side to null (unless already changed)
            if ($refArticle->getCategory() === $this) {
                $refArticle->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|HistoryCategory[]
     */
    public function getHistoryCategoriesAfter(): Collection
    {
        return $this->historyCategoriesAfter;
    }

    public function addHistoryCategoriesAfter(HistoryCategory $historyCategoriesAfter): self
    {
        if (!$this->historyCategoriesAfter->contains($historyCategoriesAfter)) {
            $this->historyCategoriesAfter[] = $historyCategoriesAfter;
            $historyCategoriesAfter->setCategoryAfter($this);
        }

        return $this;
    }

    public function removeHistoryCategoriesAfter(HistoryCategory $historyCategoriesAfter): self
    {
        if ($this->historyCategoriesAfter->contains($historyCategoriesAfter)) {
            $this->historyCategoriesAfter->removeElement($historyCategoriesAfter);
            // set the owning side to null (unless already changed)
            if ($historyCategoriesAfter->getCategoryAfter() === $this) {
                $historyCategoriesAfter->setCategoryAfter(null);
            }
        }

        return $this;
    }
}
