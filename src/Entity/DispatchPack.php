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
        $this->dispatch = $dispatch;

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
    public function getReferenceArticles(): Collection
    {
        return $this->dispatchReferenceArticles;
    }

    public function addReferenceArticle(DispatchReferenceArticle $referenceArticle): self
    {
        if (!$this->dispatchReferenceArticles->contains($referenceArticle)) {
            $this->dispatchReferenceArticles->add($referenceArticle);
            $referenceArticle->setDispatchPack($this);
        }

        return $this;
    }

    public function removeReferenceArticle(DispatchReferenceArticle $referenceArticle): self
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
