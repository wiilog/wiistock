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
     * @ORM\ManyToOne(targetEntity="App\Entity\Receptions", inversedBy="historiques")
     */
    private $reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transferts", inversedBy="historiques")
     */
    private $transfert;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparations", inversedBy="historiques")
     */
    private $preparation;

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

    public function getReceptions(): ?Receptions
    {
        return $this->reception;
    }

    public function setReceptions(?Receptions $reception): self
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
}
