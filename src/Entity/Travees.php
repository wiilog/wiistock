<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TraveesRepository")
 */
class Travees
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
     * @ORM\OneToMany(targetEntity="App\Entity\Racks", mappedBy="travees", orphanRemoval=true)
     * @Groups({"entrepots", "emplacements"})
     */
    private $racks;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Allees", inversedBy="travees")
     * @ORM\JoinColumn(nullable=false)
     */
    private $allees;

    public function __construct()
    {
        $this->racks = new ArrayCollection();
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

    /**
     * @return Collection|Racks[]
     */
    public function getRacks(): Collection
    {
        return $this->racks;
    }

    public function addRack(Racks $rack): self
    {
        if (!$this->racks->contains($rack)) {
            $this->racks[] = $rack;
            $rack->setTravees($this);
        }

        return $this;
    }

    public function removeRack(Racks $rack): self
    {
        if ($this->racks->contains($rack)) {
            $this->racks->removeElement($rack);
            // set the owning side to null (unless already changed)
            if ($rack->getTravees() === $this) {
                $rack->setTravees(null);
            }
        }

        return $this;
    }

    public function getAllees(): ?Allees
    {
        return $this->allees;
    }

    public function setAllees(?Allees $allees): self
    {
        $this->allees = $allees;

        return $this;
    }
}
