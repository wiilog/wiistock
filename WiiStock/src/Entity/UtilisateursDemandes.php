<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UtilisateursDemandesRepository")
 */
class UtilisateursDemandes
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Demandes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $utilisateur;

    public function getId()
    {
        return $this->id;
    }

    public function getDemande(): ?Demandes
    {
        return $this->demande;
    }

    public function setDemande(?Demandes $demande): self
    {
        $this->demande = $demande;

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
