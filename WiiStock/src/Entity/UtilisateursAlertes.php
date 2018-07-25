<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UtilisateursAlertesRepository")
 */
class UtilisateursAlertes
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Alertes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $alerte;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $utilisateur;

    public function getId()
    {
        return $this->id;
    }

    public function getAlerte(): ?Alertes
    {
        return $this->alerte;
    }

    public function setAlerte(?Alertes $alerte): self
    {
        $this->alerte = $alerte;

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
