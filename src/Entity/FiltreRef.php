<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FiltreRefRepository")
 */
class FiltreRef
{
    const CHAMP_FIXE_REF_ART_FOURN = 'référence article fournisseur';
    const CHAMP_FIXE_STATUT = 'Statut';
    const CHAMP_FIXE_MANAGERS = 'Gestionnaire(s)';
    const CHAMP_FIXE_LIMIT_WARNING = 'Seuil d\'alerte';
    const CHAMP_FIXE_LIMIT_SECURITY = 'Seuil de sécurité';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="FreeField", inversedBy="filters")
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

	//TODO filtres et/ou
//	/**
//	 * @ORM\Column(type="string", length=8, nullable=true)
//	 */
//    private $operator;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChampLibre(): ?FreeField
    {
        return $this->champLibre;
    }

    public function setChampLibre(?FreeField $champLibre): self
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
