<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\DispatchPackRepository')]
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

    public function __construct() {
        $this->quantity = 1;
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

}
