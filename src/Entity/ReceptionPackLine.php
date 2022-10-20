<?php

namespace App\Entity;

use App\Repository\ReceptionPackLineRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ReceptionPackLineRepository::class)]
class ReceptionPackLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Reception::class, inversedBy: 'receptionPackLines')]
    private ?Reception $reception = null;

    #[ORM\OneToMany(mappedBy: 'receptionPackLine', targetEntity: Pack::class)]
    private Collection $packs;

    public function __construct() {
        $packs = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getReception(): ?Reception {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self {
        if($this->reception && $this->reception !== $reception) {
            $this->reception->removeReceptionPackLine($this);
        }
        $this->reception = $reception;
        $reception?->addReceptionPackLine($this);

        return $this;
    }

    /**
     * @return Collection<int, Pack>
     */
    public function getPacks(): Collection {
        return $this->packs;
    }

    public function addPack(Pack $pack): self {
        if (!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
            $pack->setReceptionPackLine($this);
        }

        return $this;
    }

    public function removePack(Pack $pack): self {
        if ($this->packs->removeElement($pack)) {
            if ($pack->getReceptionPackLine() === $this) {
                $pack->setReceptionPackLine(null);
            }
        }

        return $this;
    }

    public function setPacks(?iterable $packs): self {
        foreach($this->getPacks()->toArray() as $pack) {
            $this->removePack($pack);
        }

        $this->packs = new ArrayCollection();
        foreach($packs ?? [] as $pack) {
            $this->addPack($pack);
        }

        return $this;
    }

}
