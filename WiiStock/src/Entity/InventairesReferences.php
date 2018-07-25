<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InventairesReferencesRepository")
 */
class InventairesReferences
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\References")
     * @ORM\JoinColumn(nullable=false)
     */
    private $reference;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Inventaires")
     * @ORM\JoinColumn(nullable=false)
     */
    private $inventaire;

    public function getId()
    {
        return $this->id;
    }

    public function getReference(): ?References
    {
        return $this->reference;
    }

    public function setReference(?References $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getInventaire(): ?Inventaires
    {
        return $this->inventaire;
    }

    public function setInventaire(?Inventaires $inventaire): self
    {
        $this->inventaire = $inventaire;

        return $this;
    }
}
