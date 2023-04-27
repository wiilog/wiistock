<?php

namespace App\Entity\ShippingRequest;

use App\Entity\Article;
use App\Entity\Pack;
use App\Repository\ShippingRequest\ShippingRequestPackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShippingRequestPackRepository::class)]
class ShippingRequestPack {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $size = null;

    #[ORM\OneToOne(inversedBy: 'shippingRequestPack', targetEntity: Pack::class)]
    private ?Pack $pack = null;

    #[ORM\OneToMany(mappedBy: 'shippingRequestPack', targetEntity: ShippingRequestLine::class)]
    private Collection $lines;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'packLines')]
    private ?ShippingRequest $request = null;

    public function __construct() {
        $this->shippingRequestLines = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        if($this->pack && $this->pack->getShippingRequestPack() !== $this) {
            $oldPack = $this->pack;
            $this->pack = null;
            $oldPack->setShippingRequestPack(null);
        }
        $this->pack = $pack;
        if($this->pack && $this->pack->getShippingRequestPack() !== $this) {
            $this->pack->setShippingRequestPack($this);
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
    public function getShippingRequestLines(): Collection {
        return $this->shippingRequestLines;
    }

    public function addShippingRequestLine(ShippingRequestLine $shippingRequestLine): self {
        if (!$this->shippingRequestLines->contains($shippingRequestLine)) {
            $this->shippingRequestLines[] = $shippingRequestLine;
            $shippingRequestLine->setShippingRequestPack($this);
        }

        return $this;
    }

    public function removeShippingRequestLine(ShippingRequestLine $shippingRequestLine): self {
        if ($this->shippingRequestLines->removeElement($shippingRequestLine)) {
            if ($shippingRequestLine->getShippingRequestPack() === $this) {
                $shippingRequestLine->setShippingRequestPack(null);
            }
        }

        return $this;
    }

    public function setShippingRequestLines(?iterable $shippingRequestLines): self {
        foreach($this->getShippingRequestLines()->toArray() as $shippingRequestLine) {
            $this->removeShippingRequestLine($shippingRequestLine);
        }

        $this->shippingRequestLines = new ArrayCollection();
        foreach($shippingRequestLines ?? [] as $shippingRequestLine) {
            $this->addShippingRequestLine($shippingRequestLine);
        }

        return $this;
    }
}
