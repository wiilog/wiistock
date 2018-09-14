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
     * @ORM\OneToMany(targetEntity="App\Entity\SousCategoriesVehicules", mappedBy="categorie")
     * @ORM\OrderBy({"nom" = "ASC"})
     */
    private $sousCategoriesVehicules;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Parcs", mappedBy="categorieVehicule")
     */
    private $parcs;

    public function __construct()
    {
        $this->sousCategoriesVehicules = new ArrayCollection();
        $this->parcs = new ArrayCollection();
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

    /**
     * @return Collection|Parcs[]
     */
    public function getParcs(): Collection
    {
        return $this->parcs;
    }

    public function addParc(Parcs $parc): self
    {
        if (!$this->parcs->contains($parc)) {
            $this->parcs[] = $parc;
            $parc->setCategorieVehicule($this);
        }

        return $this;
    }

    public function removeParc(Parcs $parc): self
    {
        if ($this->parcs->contains($parc)) {
            $this->parcs->removeElement($parc);
            // set the owning side to null (unless already changed)
            if ($parc->getCategorieVehicule() === $this) {
                $parc->setCategorieVehicule(null);
            }
        }

        return $this;
    }
}
