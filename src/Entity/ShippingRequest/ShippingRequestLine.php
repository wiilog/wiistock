<?php

namespace App\Entity\ShippingRequest;

use App\Entity\Article;
use App\Entity\Pack;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Repository\ShippingRequest\ShippingRequestExpectedLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShippingRequestExpectedLineRepository::class)]
class ShippingRequestLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $size = null;

    #[ORM\OneToOne(inversedBy: 'shippingRequestLine', targetEntity: Pack::class)]
    private ?Pack $pack = null;

    #[ORM\OneToMany(mappedBy: 'shippingRequestLine', targetEntity: Article::class)]
    private Collection $articles;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'lines')]
    private ?ShippingRequest $request = null;

    public function __construct() {
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        if($this->pack && $this->pack->getShippingRequestLine() !== $this) {
            $oldPack = $this->pack;
            $this->pack = null;
            $oldPack->setShippingRequestLine(null);
        }
        $this->pack = $pack;
        if($this->pack && $this->pack->getShippingRequestLine() !== $this) {
            $this->pack->setShippingRequestLine($this);
        }

        return $this;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self {
        $this->quantity = $quantity;
        return $this;
    }

    public function getSize(): ?float {
        return $this->size;
    }

    public function setSize(?float $size): self {
        $this->size = $size;
        return $this;
    }

    public function getRequest(): ?ShippingRequest {
        return $this->request;
    }

    public function setRequest(?ShippingRequest $request): self {
        if($this->request && $this->request !== $request) {
            $this->request->removeLine($this);
        }
        $this->request = $request;
        $request?->addLine($this);

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection {
        return $this->articles;
    }

    public function addArticle(Article $article): self {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setShippingRequestLine($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        if ($this->articles->removeElement($article)) {
            if ($article->getShippingRequestLine() === $this) {
                $article->setShippingRequestLine(null);
            }
        }

        return $this;
    }

    public function setArticles(?iterable $articles): self {
        foreach($this->getArticles()->toArray() as $example) {
            $this->removeArticle($example);
        }

        $this->articles = new ArrayCollection();
        foreach($articles ?? [] as $example) {
            $this->addArticle($example);
        }

        return $this;
    }

}
