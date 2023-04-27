<?php

namespace App\Entity\ShippingRequest;

use App\Entity\Article;
use App\Repository\ShippingRequest\ShippingRequestLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShippingRequestLineRepository::class)]
class ShippingRequestLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $quantity = null;

    #[ORM\OneToOne(inversedBy: 'shippingRequestLine', targetEntity: Article::class)]
    private ?Article $article;

    #[ORM\ManyToOne(targetEntity: ShippingRequestPack::class, inversedBy: 'lines')]
    private ?ShippingRequestPack $shippingRequestPack;

    #[ORM\ManyToOne(targetEntity: ShippingRequestExpectedLine::class, inversedBy: 'lines')]
    private ?ShippingRequestExpectedLine $expectedLine;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self {
        $this->quantity = $quantity;
        return $this;
    }

    public function getArticle(): Article {
        return $this->article;
    }

    public function setArticle(Article $article): self {
        $this->article = $article;
        return $this;
    }

    public function getShippingRequestPack(): ?ShippingRequestPack {
        return $this->shippingRequestPack;
    }

    public function setShippingRequestPack(?ShippingRequestPack $shippingRequestPack): self {
        $this->shippingRequestPack = $shippingRequestPack;
        return $this;
    }

    public function getExpectedLine(): ?ShippingRequestExpectedLine {
        return $this->expectedLine;
    }

    public function setExpectedLine(?ShippingRequestExpectedLine $expectedLine): self {
        $this->expectedLine = $expectedLine;
        return $this;
    }
}
