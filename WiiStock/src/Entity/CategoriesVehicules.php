<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CategoriesVehiculesRepository")
 */
class CategoriesVehicules
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Parcs", inversedBy="categoriesVehicules")
     */
    private $parc;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\SousCategoriesVehicules", mappedBy="categorie")
     * @Groups({"parc"})
     */
    private $sousCategoriesVehicules;

    public function __construct()
    {
        $this->sousCategoriesVehicules = new ArrayCollection();
    }

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

    public function getParc(): ?Parcs
    {
        return $this->parc;
    }

    public function setParc(?Parcs $parc): self
    {
        $this->parc = $parc;

        return $this;
    }

    /**
     * @return Collection|SousCategoriesVehicules[]
     */
    public function getSousCategoriesVehicules(): Collection
    {
        return $this->sousCategoriesVehicules;
    }

    public function addSousCategoriesVehicule(SousCategoriesVehicules $sousCategoriesVehicule): self
    {
        if (!$this->sousCategoriesVehicules->contains($sousCategoriesVehicule)) {
            $this->sousCategoriesVehicules[] = $sousCategoriesVehicule;
            $sousCategoriesVehicule->setCategorie($this);
        }

        return $this;
    }

    public function removeSousCategoriesVehicule(SousCategoriesVehicules $sousCategoriesVehicule): self
    {
        if ($this->sousCategoriesVehicules->contains($sousCategoriesVehicule)) {
            $this->sousCategoriesVehicules->removeElement($sousCategoriesVehicule);
            // set the owning side to null (unless already changed)
            if ($sousCategoriesVehicule->getCategorie() === $this) {
                $sousCategoriesVehicule->setCategorie(null);
            }
        }

        return $this;
    }
}
