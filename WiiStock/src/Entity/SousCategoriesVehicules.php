<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SousCategoriesVehiculesRepository")
 */
class SousCategoriesVehicules
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"parc"})
     */
    private $nom;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"parc"})
     */
    private $code;


    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Parcs", inversedBy="sousCategoriesVehicules")
     */
    private $parc;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoriesVehicules", inversedBy="sousCategoriesVehicules")
     */
    private $categorie;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function setCode(int $code): self
    {
        $this->code = $code;

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

    public function getCategorie(): ?CategoriesVehicules
    {
        return $this->categorie;
    }

    public function setCategorie(?CategoriesVehicules $categorie): self
    {
        $this->categorie = $categorie;

        return $this;
    }
}
