<?php

namespace App\Entity\DeliveryRequest;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\Pack;
use App\Repository\DeliveryRequest\DeliveryRequestArticleLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestArticleLineRepository::class)]
class DeliveryRequestArticleLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantityToPick = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pickedQuantity = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'deliveryRequestLines')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'articleLines')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Demande $request = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'deliveryRequestArticleLines')]
    private ?Emplacement $targetLocationPicking = null;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    private ?Pack $pack = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantityToPick(): ?int {
        return $this->quantityToPick;
    }

    public function setQuantityToPick(?int $quantityToPick): self {
        $this->quantityToPick = $quantityToPick;

        return $this;
    }

    public function setPickedQuantity(?int $pickedQuantity): self {
        $this->pickedQuantity = $pickedQuantity;

        return $this;
    }

    public function getPickedQuantity(): ?int {
        return $this->pickedQuantity;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        if($this->article && $this->article !== $article) {
            $this->article->removeDeliveryRequestLine($this);
        }

        $this->article = $article;

        if($article) {
            $article->addDeliveryRequestLine($this);
        }

        return $this;
    }

    public function getRequest(): ?Demande {
        return $this->request;
    }

    public function setRequest(?Demande $request): self {
        if($this->request && $this->request !== $request) {
            $this->request->removeArticleLine($this);
        }

        $this->request = $request;

        if($request) {
            $request->addArticleLine($this);
        }

        return $this;
    }

    public function getTargetLocationPicking(): ?Emplacement {
        return $this->targetLocationPicking;
    }

    public function setTargetLocationPicking(?Emplacement $targetLocationPicking): self {
        if($this->targetLocationPicking && $this->targetLocationPicking !== $targetLocationPicking) {
            $this->targetLocationPicking->removeDeliveryRequestArticleLine($this);
        }

        $this->targetLocationPicking = $targetLocationPicking;

        if($targetLocationPicking) {
            $targetLocationPicking->addDeliveryRequestArticleLine($this);
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
