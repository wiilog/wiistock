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

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    private ?Pack $pack = null;

    public function __construct() {
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

    public function getPack(): Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        $this->pack = $pack;
        return $this;
    }

}
