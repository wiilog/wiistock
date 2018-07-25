<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticlesRepository")
 */
class Articles
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $etat;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacements")
     */
    private $emplacement;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zones")
     */
    private $zone;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais")
     */
    private $quai;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\References")
     * @ORM\JoinColumn(nullable=false)
     */
    private $reference;

    public function getId()
    {
        return $this->id;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    public function getEmplacement(): ?Emplacements
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacements $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    public function getZone(): ?Zones
    {
        return $this->zone;
    }

    public function setZone(?Zones $zone): self
    {
        $this->zone = $zone;

        return $this;
    }

    public function getQuai(): ?Quais
    {
        return $this->quai;
    }

    public function setQuai(?Quais $quai): self
    {
        $this->quai = $quai;

        return $this;
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
}
