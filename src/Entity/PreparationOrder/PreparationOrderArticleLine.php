<?php

namespace App\Entity\PreparationOrder;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\Pack;
use App\Repository\PreparationOrder\PreparationOrderArticleLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreparationOrderArticleLineRepository::class)]
class PreparationOrderArticleLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $quantityToPick = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pickedQuantity = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'preparationOrderLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Preparation::class, inversedBy: 'articleLines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Preparation $preparation = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'preparationOrderArticleLines')]
    private ?Emplacement $targetLocationPicking = null;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    private ?Pack $pack = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantityToPick(): ?int {
        return $this->quantityToPick;
    }

    public function setQuantityToPick(int $quantityToPick): self {
        $this->quantityToPick = $quantityToPick;

        return $this;
    }

    public function getPickedQuantity(): ?int {
        return $this->pickedQuantity;
    }

    public function setPickedQuantity(?int $pickedQuantity): self {
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

    public function getPreparation(): ?Preparation {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self {
        if($this->preparation && $this->preparation !== $preparation) {
            $this->preparation->removeArticleLine($this);
        }
        $this->preparation = $preparation;
        if($preparation) {
            $preparation->addArticleLine($this);
        }

        return $this;
    }

    public function getTargetLocationPicking(): ?Emplacement {
        return $this->targetLocationPicking;
    }

    public function setTargetLocationPicking(?Emplacement $targetLocationPicking): self {
        if($this->targetLocationPicking && $this->targetLocationPicking !== $targetLocationPicking) {
            $this->targetLocationPicking->removePreparationOrderArticleLine($this);
        }

        $this->targetLocationPicking = $targetLocationPicking;

        if($targetLocationPicking) {
            $targetLocationPicking->addPreparationOrderArticleLine($this);
        }

        return $this;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        $this->pack = $pack;
        return $this;
    }

}
