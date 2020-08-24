<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PackAcheminementRepository")
 */
class PackAcheminement
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Pack", inversedBy="packAcheminements")
     * @ORM\JoinColumn(nullable=false)
     */
    private $pack;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Acheminements", inversedBy="packAcheminements")
     * @ORM\JoinColumn(nullable=false)
     */
    private $acheminement;

    /**
     * @ORM\Column(type="boolean", options={default: false})
     */
    private $treated;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self
    {
        $this->pack = $pack;

        return $this;
    }

    public function getAcheminement(): ?Acheminements
    {
        return $this->acheminement;
    }

    public function setAcheminement(?Acheminements $acheminement): self
    {
        $this->acheminement = $acheminement;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTreated(): ?bool
    {
        return $this->treated;
    }

    public function setTreated(bool $treated): self
    {
        $this->treated = $treated;

        return $this;
    }
}
