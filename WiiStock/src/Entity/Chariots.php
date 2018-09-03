<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;


/**
 * @ORM\Entity(repositoryClass="App\Repository\ChariotsRepository")
 */
class Chariots
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"parc"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"parc"})
     */
    private $proprietaire;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Groups({"parc"})
     */
    private $n_serie;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Parcs", inversedBy="chariots", cascade={"persist", "remove"})
     * @Groups({"parc"})
     */
    private $parc;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProprietaire(): ?string
    {
        return $this->proprietaire;
    }

    public function setProprietaire(string $proprietaire): self
    {
        $this->proprietaire = $proprietaire;

        return $this;
    }

    public function getNSerie(): ?string
    {
        return $this->n_serie;
    }

    public function setNSerie(string $n_serie): self
    {
        $this->n_serie = $n_serie;

        return $this;
    }

    public function getParc(): ?Parcs
    {
        return $this->parc;
    }

    public function setParc(?Parcs $parc): self
    {
        $this->parc = $parc;

        return $this;
    }
}
