<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VehiculesRepository")
 */
class Vehicules
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $immatriculation;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $genre;

    /**
     * @ORM\Column(type="float")
     */
    private $ptac;

    /**
     * @ORM\Column(type="float")
     */
    private $ptr;

    /**
     * @ORM\Column(type="integer")
     */
    private $puissance_fiscale;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Parcs", inversedBy="vehicules", cascade={"persist", "remove"} , nullable="false")
     */
    private $parc;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImmatriculation(): ?string
    {
        return $this->immatriculation;
    }

    public function setImmatriculation(string $immatriculation): self
    {
        $this->immatriculation = $immatriculation;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): self
    {
        $this->genre = $genre;

        return $this;
    }

    public function getPtac(): ?float
    {
        return $this->ptac;
    }

    public function setPtac(float $ptac): self
    {
        $this->ptac = $ptac;

        return $this;
    }

    public function getPtr(): ?float
    {
        return $this->ptr;
    }

    public function setPtr(float $ptr): self
    {
        $this->ptr = $ptr;

        return $this;
    }

    public function getPuissanceFiscale(): ?int
    {
        return $this->puissance_fiscale;
    }

    public function setPuissanceFiscale(int $puissance_fiscale): self
    {
        $this->puissance_fiscale = $puissance_fiscale;

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
