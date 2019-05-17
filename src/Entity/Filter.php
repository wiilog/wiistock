<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FilterRepository")
 */
class Filter
{
    const CHAMP_FIXE_REF_ART_FOURN = 'référence article fournisseur';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ChampsLibre", inversedBy="filters")
     * @ORM\JoinColumn(nullable=true)
     */
    private $champLibre;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $champFixe;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $value;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="filters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $utilisateur;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChampLibre(): ?ChampsLibre
    {
        return $this->champLibre;
    }

    public function setChampLibre(?ChampsLibre $champLibre): self
    {
        $this->champLibre = $champLibre;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getChampFixe(): ?string
    {
        return $this->champFixe;
    }

    public function setChampFixe(?string $champFixe): self
    {
        $this->champFixe = $champFixe;

        return $this;
    }
}
