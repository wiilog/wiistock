<?php

namespace App\Entity;

use App\Entity\Tracking\Pack;
use App\Repository\DispatchPackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DispatchPackRepository::class)]
class DispatchPack {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private ?int $quantity;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'dispatchPacks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pack $pack = null;

    #[ORM\ManyToOne(targetEntity: Dispatch::class, inversedBy: 'dispatchPacks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dispatch $dispatch = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private ?bool $treated = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $width = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $height = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $length = null;

    #[ORM\OneToMany(mappedBy: 'dispatchPack', targetEntity: DispatchReferenceArticle::class)]
    private Collection $dispatchReferenceArticles;

    public function __construct() {
        $this->quantity = 1;
        $this->dispatchReferenceArticles = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        $this->pack = $pack;

        return $this;
    }

    public function getDispatch(): ?Dispatch {
        return $this->dispatch;
    }

    public function setDispatch(?Dispatch $dispatch): self {
        if($this->dispatch && $this->dispatch !== $dispatch) {
            $this->dispatch->removeDispatchPack($this);
        }
        $this->dispatch = $dispatch;
        $dispatch?->addDispatchPack($this);

        return $this;
    }

    public function setQuantity(int $quantity): self {
        $this->quantity = $quantity;
        return $this;
    }

    public function getQuantity(): int {
        return $this->quantity;
    }

    public function isTreated(): ?bool {
        return $this->treated;
    }

    public function setTreated(bool $treated): self {
        $this->treated = $treated;

        return $this;
    }

    /**
     * @return Collection<int, DispatchReferenceArticle>
     */
    public function getDispatchReferenceArticles(): Collection
    {
        return $this->dispatchReferenceArticles;
    }

    public function getDispatchReferenceArticle(ReferenceArticle $referenceArticle): ?DispatchReferenceArticle {
        /** @var DispatchReferenceArticle $dispatchReferenceArticle */
        foreach ($this->dispatchReferenceArticles as $dispatchReferenceArticle){
            if($dispatchReferenceArticle->getReferenceArticle() === $referenceArticle){
                return $dispatchReferenceArticle;
            }
        }
        return null;
    }

    public function addDispatchReferenceArticles(DispatchReferenceArticle $referenceArticle): self
    {
        if (!$this->dispatchReferenceArticles->contains($referenceArticle)) {
            $this->dispatchReferenceArticles->add($referenceArticle);
            $referenceArticle->setDispatchPack($this);
        }

        return $this;
    }

    public function removeDispatchReferenceArticles(DispatchReferenceArticle $referenceArticle): self
    {
        if ($this->dispatchReferenceArticles->removeElement($referenceArticle)) {
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getDispatchPack() === $this) {
                $referenceArticle->setDispatchPack(null);
            }
        }

        return $this;
    }

    public function setWidth(?float $width): self {
        $this->width = $width;
        return $this;
    }

    public function getWidth(): ?float {
        return $this->width;
    }

    public function setHeight(?float $height): self {
        $this->height = $height;
        return $this;
    }

    public function getHeight(): ?float {
        return $this->height;
    }

    public function setLength(?float $length): self {
        $this->length = $length;
        return $this;
    }

    public function getLength(): ?float {
        return $this->length;
    }
}
