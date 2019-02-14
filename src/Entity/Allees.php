<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AlleesRepository")
 */
class Allees
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
     * @ORM\OneToMany(targetEntity="App\Entity\Travees", mappedBy="allees", orphanRemoval=true)
     * @Groups({"entrepots", "emplacements"})
     */
    private $travees;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Entrepots", inversedBy="allees")
     * @ORM\JoinColumn(nullable=false)
     */
    private $entrepots;

    public function __construct()
    {
        $this->travees = new ArrayCollection();
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
     * @return Collection|Travees[]
     */
    public function getTravees(): Collection
    {
        return $this->travees;
    }

    public function addTravee(Travees $travee): self
    {
        if (!$this->travees->contains($travee)) {
            $this->travees[] = $travee;
            $travee->setAllees($this);
        }

        return $this;
    }

    public function removeTravee(Travees $travee): self
    {
        if ($this->travees->contains($travee)) {
            $this->travees->removeElement($travee);
            // set the owning side to null (unless already changed)
            if ($travee->getAllees() === $this) {
                $travee->setAllees(null);
            }
        }

        return $this;
    }

    public function getEntrepots(): ?Entrepots
    {
        return $this->entrepots;
    }

    public function setEntrepots(?Entrepots $entrepots): self
    {
        $this->entrepots = $entrepots;

        return $this;
    }
}
