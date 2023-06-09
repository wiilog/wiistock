<?php

namespace App\Entity;

use App\Repository\DispatchPackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DispatchPackRepository::class)]
class DispatchPack {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private $quantity;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'dispatchPacks')]
    #[ORM\JoinColumn(nullable: false)]
    private $pack;

    #[ORM\ManyToOne(targetEntity: Dispatch::class, inversedBy: 'dispatchPacks')]
    #[ORM\JoinColumn(nullable: false)]
    private $dispatch;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private $treated;

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

}
