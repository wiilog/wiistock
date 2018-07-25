<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UtilisateursInventairesRepository")
 */
class UtilisateursInventaires
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Inventaires")
     * @ORM\JoinColumn(nullable=false)
     */
    private $inventaire;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $utilisateur;

    public function getId()
    {
        return $this->id;
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

    public function getUtilisateur(): ?Utilisateurs
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateurs $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }
}
