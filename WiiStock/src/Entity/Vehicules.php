<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;


/**
 * @ORM\Entity(repositoryClass="App\Repository\VehiculesRepository")
 */
class Vehicules
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"parc"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Groups({"parc"})
     */
    private $immatriculation;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"parc"})
     */
    private $genre;

    /**
     * @ORM\Column(type="float")
     * @Groups({"parc"})
     */
    private $ptac;

    /**
     * @ORM\Column(type="float")
     * @Groups({"parc"})
     */
    private $ptr;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"parc"})
     */
    private $puissance_fiscale;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Parcs", inversedBy="vehicules", cascade={"persist", "remove"})
     * @Groups({"parc"})
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
