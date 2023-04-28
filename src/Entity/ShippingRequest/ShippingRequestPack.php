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

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $size = null;

    #[ORM\OneToOne(inversedBy: 'shippingRequestPack', targetEntity: Pack::class)]
    private ?Pack $pack = null;

    #[ORM\OneToMany(mappedBy: 'shippingPack', targetEntity: ShippingRequestLine::class)]
    private Collection $lines;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'packLines')]
    private ?ShippingRequest $request = null;

    public function __construct() {
        $this->lines = new ArrayCollection();
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
            $this->request->removePackLine($this);
        }
        $this->request = $request;
        $request?->addPackLine($this);

        return $this;
    }

    /**
     * @return Collection<int, ShippingRequestLine>
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(ShippingRequestLine $line): self {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setShippingPack($this);
        }

        return $this;
    }

    public function removeLine(ShippingRequestLine $line): self {
        if ($this->lines->removeElement($line)) {
            if ($line->getShippingPack() === $this) {
                $line->setShippingPack(null);
            }
        }

        return $this;
    }

    public function setLines(?iterable $lines): self {
        foreach($this->getlines()->toArray() as $line) {
            $this->removeline($line);
        }

        $this->lines = new ArrayCollection();
        foreach($lines ?? [] as $line) {
            $this->addLine($line);
        }

        return $this;
    }
}
