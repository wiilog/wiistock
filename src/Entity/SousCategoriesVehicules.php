<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoriesVehicules", inversedBy="sousCategoriesVehicules")
     */
    private $categorie;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Parcs", mappedBy="sousCategorieVehicule")
     */
    private $parcs;

    public function __construct()
    {
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

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function setCode(int $code): self
    {
        $this->code = $code;

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
            $parc->setSousCategorieVehicule($this);
        }

        return $this;
    }

    public function removeParc(Parcs $parc): self
    {
        if ($this->parcs->contains($parc)) {
            $this->parcs->removeElement($parc);
            // set the owning side to null (unless already changed)
            if ($parc->getSousCategorieVehicule() === $this) {
                $parc->setSousCategorieVehicule(null);
            }
        }

        return $this;
    }
}
