<?php

namespace App\Entity\PreparationOrder;

use App\Entity\Article;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PreparationOrder\PreparationOrderArticleLineRepository")
 */
class PreparationOrderArticleLine
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $quantity = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $pickedQuantity = null;

    /**
     * @ORM\ManyToOne(targetEntity=Article::class, inversedBy="preparationOrderLines")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Article $article = null;

    /**
     * @ORM\ManyToOne(targetEntity=Preparation::class, inversedBy="articleLines")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Preparation $preparation = null;

    public function getId(): ?int {
        return $this->id;
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

    public function getPickedQuantity(): ?int {
        return $this->pickedQuantity;
    }

    public function setPickedQuantity(?int $pickedQuantity): self
    {
        $this->pickedQuantity = $pickedQuantity;

        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        if($this->article && $this->article !== $article) {
            $this->article->removePreparationOrderLine($this);
        }

        $this->article = $article;

        if($article) {
            $article->addPreparationOrderLine($this);
        }

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        if($this->preparation && $this->preparation !== $preparation) {
            $this->preparation->removeArticleLine($this);
        }
        $this->preparation = $preparation;
        if($preparation) {
            $preparation->addArticleLine($this);
        }

        return $this;
    }
}
