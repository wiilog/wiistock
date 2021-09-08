<?php

namespace App\Entity\DeliveryRequest;

use App\Entity\Article;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\DeliveryRequest\DeliveryRequestArticleLineRepository;

/**
 * @ORM\Entity(repositoryClass=DeliveryRequestArticleLineRepository::class)
 */
class DeliveryRequestArticleLine
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $quantity = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $pickedQuantity = null;

    /**
     * @ORM\ManyToOne(targetEntity=Article::class, inversedBy="deliveryRequestLines")
     */
    private ?Article $article = null;

    /**
     * @ORM\ManyToOne(targetEntity=Demande::class, inversedBy="articleLines")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private ?Demande $request = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function setPickedQuantity(?int $pickedQuantity): self
    {
        $this->pickedQuantity = $pickedQuantity;

        return $this;
    }

    public function getPickedQuantity(): ?int {
        return $this->pickedQuantity;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
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

    public function setRequest(?Demande $request): self
    {
        if($this->request && $this->request !== $request) {
            $this->request->removeArticleLine($this);
        }

        $this->request = $request;

        if($request) {
            $request->addArticleLine($this);
        }

        return $this;
    }

    public function getToSplit(): ?bool
    {
        return $this->toSplit;
    }

    public function setToSplit(?bool $toSplit): self
    {
        $this->toSplit = $toSplit;

        return $this;
    }

    public function getQuantitePrelevee(): ?int
    {
        return $this->quantitePrelevee;
    }

    public function setQuantitePrelevee(?int $quantitePrelevee): self
    {
        $this->quantitePrelevee = $quantitePrelevee;

        return $this;
    }

}
