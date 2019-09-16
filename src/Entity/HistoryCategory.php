<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HistoryCategoryRepository")
 */
class HistoryCategory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoryInv", inversedBy="historyCategories")
     * @ORM\JoinColumn(nullable=false)
     */
    private $categoryBefore;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoryInv", inversedBy="historyCategoriesAfter")
     * @ORM\JoinColumn(nullable=false)
     */
    private $categoryAfter;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Utilisateur", mappedBy="historyCategory")
     */
    private $operator;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", inversedBy="historyCategories")
     */
    private $article;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="historyCategories")
     */
    private $refArticle;

    public function __construct()
    {
        $this->operator = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategoryBefore(): ?CategoryInv
    {
        return $this->categoryBefore;
    }

    public function setCategoryBefore(?CategoryInv $categoryBefore): self
    {
        $this->categoryBefore = $categoryBefore;

        return $this;
    }

    public function getCategoryAfter(): ?CategoryInv
    {
        return $this->categoryAfter;
    }

    public function setCategoryAfter(?CategoryInv $categoryAfter): self
    {
        $this->categoryAfter = $categoryAfter;

        return $this;
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

    /**
     * @return Collection|Utilisateur[]
     */
    public function getOperator(): Collection
    {
        return $this->operator;
    }

    public function addOperator(Utilisateur $operator): self
    {
        if (!$this->operator->contains($operator)) {
            $this->operator[] = $operator;
            $operator->setHistoryCategory($this);
        }

        return $this;
    }

    public function removeOperator(Utilisateur $operator): self
    {
        if ($this->operator->contains($operator)) {
            $this->operator->removeElement($operator);
            // set the owning side to null (unless already changed)
            if ($operator->getHistoryCategory() === $this) {
                $operator->setHistoryCategory(null);
            }
        }

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

    public function getRefArticle(): ?ReferenceArticle
    {
        return $this->refArticle;
    }

    public function setRefArticle(?ReferenceArticle $refArticle): self
    {
        $this->refArticle = $refArticle;

        return $this;
    }
}
