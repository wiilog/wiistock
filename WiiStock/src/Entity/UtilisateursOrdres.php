<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UtilisateursOrdresRepository")
 */
class UtilisateursOrdres
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Ordres")
     * @ORM\JoinColumn(nullable=false)
     */
    private $ordre;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $utilisateur;

    public function getId()
    {
        return $this->id;
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
