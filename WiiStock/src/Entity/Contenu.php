<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ContenuRepository")
 */
class Contenu
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\reception", inversedBy="transfert")
     */
    private $reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transfert", inversedBy="contenus")
     */
    private $transfert;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparations", inversedBy="contenus")
     */
    private $preparation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ZoneQuai", inversedBy="contenus")
     */
    private $zone_quai;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getReception(): ?reception
    {
        return $this->reception;
    }

    public function setReception(?reception $reception): self
    {
        $this->reception = $reception;

        return $this;
    }

    public function getTransfert(): ?Transfert
    {
        return $this->transfert;
    }

    public function setTransfert(?Transfert $transfert): self
    {
        $this->transfert = $transfert;

        return $this;
    }

    public function getPreparation(): ?Preparations
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparations $preparation): self
    {
        $this->preparation = $preparation;

        return $this;
    }

    public function getZoneQuai(): ?ZoneQuai
    {
        return $this->zone_quai;
    }

    public function setZoneQuai(?ZoneQuai $zone_quai): self
    {
        $this->zone_quai = $zone_quai;

        return $this;
    }
}
