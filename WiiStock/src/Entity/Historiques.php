<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HistoriquesRepository")
 */
class Historiques
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Receptions", inversedBy="historiques")
     */
    private $reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparations", inversedBy="historiques")
     */
    private $preparation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transferts", inversedBy="historiques")
     */
    private $transfert;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getReception(): ?Receptions
    {
        return $this->reception;
    }

    public function setReception(?Receptions $reception): self
    {
        $this->reception = $reception;

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

    public function getTransfert(): ?Transferts
    {
        return $this->transfert;
    }

    public function setTransfert(?Transferts $transfert): self
    {
        $this->transfert = $transfert;

        return $this;
    }
}
