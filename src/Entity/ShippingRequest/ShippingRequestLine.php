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

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'shippingRequestLines')]
    private ?ReferenceArticle $reference = null;

    #[ORM\OneToOne(inversedBy: 'shippingRequestLine', targetEntity: Article::class)]
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
            $this->setArticle($articleOrReference);
        } else {
            $this->setReference($articleOrReference);
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function setArticle(Article $article): self {
        if($this->reference){
            throw new Exception("Can't set article if reference is set");
        }

        if($this->article && $this->article->getShippingRequestLine() !== $this) {
            $oldArticle = $this->article;
            $this->article = null;
            $oldArticle->setShippingRequestLine(null);
        }
        $this->article = $article;
        if($this->article && $this->article->getShippingRequestLine() !== $this) {
            $this->article->setShippingRequestLine($this);
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function setReference(?ReferenceArticle $reference): self
    {
        if($this->article){
            throw new Exception("Can't set reference if article is set");
        }

        if($this->reference && $this->reference !== $reference) {
            $this->reference->removeShippingRequestLines($this);
        }
        $this->reference = $reference;
        $reference?->addShippingRequestLines($this);

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
