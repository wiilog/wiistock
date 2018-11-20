<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RacksRepository")
 */
class Racks
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"entrepots", "emplacements"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"entrepots", "emplacements"})
     */
    private $nom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Travees", inversedBy="racks")
     * @ORM\JoinColumn(nullable=false)
     */
    private $travees;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Emplacements", mappedBy="racks", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"entrepots", "emplacements"})
     */
    private $emplacements;

    public function __construct()
    {
        $this->emplacements = new ArrayCollection();
    }

    public function getId()
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

    public function getTravees(): ?Travees
    {
        return $this->travees;
    }

    public function setTravees(?Travees $travees): self
    {
        $this->travees = $travees;

        return $this;
    }

    /**
     * @return Collection|Emplacements[]
     */
    public function getEmplacements(): Collection
    {
        return $this->emplacements;
    }

    public function addEmplacement(Emplacements $emplacement): self
    {
        if (!$this->emplacements->contains($emplacement)) {
            $this->emplacements[] = $emplacement;
            $emplacement->setRacks($this);
        }

        return $this;
    }

    public function removeEmplacement(Emplacements $emplacement): self
    {
        if ($this->emplacements->contains($emplacement)) {
            $this->emplacements->removeElement($emplacement);
            // set the owning side to null (unless already changed)
            if ($emplacement->getRacks() === $this) {
                $emplacement->setRacks(null);
            }
        }

        return $this;
    }
}
