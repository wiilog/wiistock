<?php

namespace App\Entity;

use App\Helper\FormatHelper;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceptionTracaRepository")
 */
class ReceptionTraca
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $arrivage;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $number;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="receptionsTraca")
     */
    private $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArrivage(): ?string
    {
        return $this->arrivage;
    }

    public function setArrivage(?string $arrivage): self
    {
        $this->arrivage = $arrivage;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        $this->user = $user;

        return $this;
    }


    public function serialize(): array {
        return [
            'creationDate' => FormatHelper::datetime($this->getDateCreation()),
            'arrival' => $this->getArrivage(),
            'number' => $this->getNumber(),
            'user' => FormatHelper::user($this->getUser())
        ];
    }
}
