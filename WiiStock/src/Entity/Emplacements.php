<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use JsonSerializable;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EmplacementsRepository")
 */
class Emplacements implements JsonSerializable
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

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Contenu", mappedBy="emplacement")
     */
    private $contenus;

    public function jsonSerialize()
    {
        return array(
            'nom' => $this->nom,
        );
    }

    public function __construct()
    {
        $this->contenus = new ArrayCollection();
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

    public function getRacks(): ?Racks
    {
        return $this->racks;
    }

    public function setRacks(?Racks $racks): self
    {
        $this->racks = $racks;

        return $this;
    }

    /**
     * @return Collection|Contenu[]
     */
    public function getContenus(): Collection
    {
        return $this->contenus;
    }

    public function addContenus(Contenu $contenus): self
    {
        if (!$this->contenus->contains($contenus)) {
            $this->contenus[] = $contenus;
            $contenus->setEmplacement($this);
        }

        return $this;
    }

    public function removeContenus(Contenu $contenus): self
    {
        if ($this->contenus->contains($contenus)) {
            $this->contenus->removeElement($contenus);
            // set the owning side to null (unless already changed)
            if ($contenus->getEmplacement() === $this) {
                $contenus->setEmplacement(null);
            }
        }

        return $this;
    }
}
