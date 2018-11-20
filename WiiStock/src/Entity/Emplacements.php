<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EmplacementsRepository")
 */
class Emplacements
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"emplacements"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"emplacements", "entrepots"})
     */
    private $nom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Racks", inversedBy="emplacements")
     */
    private $racks;

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

    public function getRacks(): ?Racks
    {
        return $this->racks;
    }

    public function setRacks(?Racks $racks): self
    {
        $this->racks = $racks;

        return $this;
    }
}
