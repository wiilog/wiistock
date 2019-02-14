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
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="racks")
     */
    private $emplacement;


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

    public function getEmplacement(): ?Emplacement
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }

}
