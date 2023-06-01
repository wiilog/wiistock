<?php

namespace App\Entity\ShippingRequest;

use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Repository\ShippingRequest\ShippingRequestLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;

#[ORM\Entity(repositoryClass: ShippingRequestLineRepository::class)]
class ShippingRequestLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $quantity = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ReferenceArticle $reference = null;

    #[ORM\OneToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: ShippingRequestPack::class, cascade: ['persist'], inversedBy: 'lines')]
    private ?ShippingRequestPack $shippingPack = null;

    #[ORM\ManyToOne(targetEntity: ShippingRequestExpectedLine::class, inversedBy: 'lines')]
    private ?ShippingRequestExpectedLine $expectedLine = null;

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

    public function getArticleOrReference(): Article|ReferenceArticle|null {
        return $this->article ?? $this->reference;
    }

    /**
     * @throws Exception
     */
    public function setArticleOrReference(Article|ReferenceArticle $articleOrReference): self {
        if($articleOrReference instanceof Article) {
            $this
                ->setReference(null)
                ->setArticle($articleOrReference);
        } else {
            $this
                ->setArticle(null)
                ->setReference($articleOrReference);
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function setArticle(?Article $article): self {
        if($this->reference && $article){
            throw new Exception("Can't set article if reference is set");
        }

        $this->article = $article;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function setReference(?ReferenceArticle $reference): self
    {
        if($this->article && $reference){
            throw new Exception("Can't set reference if article is set");
        }

        $this->reference = $reference;
        return $this;
    }

    public function getShippingPack(): ?ShippingRequestPack {
        return $this->shippingPack;
    }

    public function setShippingPack(?ShippingRequestPack $shippingPack): self {
        if($this->shippingPack && $this->shippingPack !== $shippingPack) {
            $this->shippingPack->removeLine($this);
        }
        $this->shippingPack = $shippingPack;
        $shippingPack?->addLine($this);

        return $this;
    }

    public function getExpectedLine(): ?ShippingRequestExpectedLine {
        return $this->expectedLine;
    }

    public function setExpectedLine(?ShippingRequestExpectedLine $expectedLine): self {
        if($this->expectedLine && $this->expectedLine !== $expectedLine) {
            $this->expectedLine->removeLine($this);
        }
        $this->expectedLine = $expectedLine;
        $expectedLine?->addLine($this);

        return $this;
    }
}
