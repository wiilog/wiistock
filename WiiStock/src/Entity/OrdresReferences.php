<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OrdresReferencesRepository")
 */
class OrdresReferences
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Ordres")
     * @ORM\JoinColumn(nullable=false)
     */
    private $ordre;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantite;

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

    public function getOrdre(): ?Ordres
    {
        return $this->ordre;
    }

    public function setOrdre(?Ordres $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }
}
