<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SitesRepository")
 */
class Sites
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
    private $nom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Filiales", inversedBy="sites")
     * @ORM\JoinColumn(nullable=false)
     */
    private $filiale;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Parcs", inversedBy="sites")
     */
    private $parc;

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

    public function getFiliale(): ?Filiales
    {
        return $this->filiale;
    }

    public function setFiliale(?Filiales $filiale): self
    {
        $this->filiale = $filiale;

        return $this;
    }

    public function getParc(): ?Parcs
    {
        return $this->parc;
    }

    public function setParc(?Parcs $parc): self
    {
        $this->parc = $parc;

        return $this;
    }
}
