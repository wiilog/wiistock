<?php

namespace App\Entity;

use App\Repository\FiltreRefRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FiltreRefRepository::class)]
class FiltreRef {

    const FIXED_FIELD_REF_ART_FOURN = 'référence article fournisseur';
    const FIXED_FIELD_STATUS = 'Statut';
    const FIXED_FIELD_MANAGERS = 'Gestionnaire(s)';
    const FIXED_FIELD_VISIBILITY_GROUP = 'Groupe de visibilité';
    const FIXED_FIELD_PROVIDER_CODE = 'Code fournisseur';
    const FIXED_FIELD_PROVIDER_LABEL = 'Nom fournisseur';
    const FIXED_FIELD_EDITED_BY = 'Dernière modification par';
    const FIXED_FIELD_CREATED_BY = 'Créée par';
    const FIXED_FIELD_BUYER = 'Acheteur';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'FreeField', inversedBy: 'filters')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FreeField $champLibre = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $champFixe = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $value = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'filters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getChampLibre(): ?FreeField {
        return $this->champLibre;
    }

    public function setChampLibre(?FreeField $champLibre): self {
        $this->champLibre = $champLibre;

        return $this;
    }

    public function getValue(): ?string {
        return $this->value;
    }

    public function setValue(?string $value): self {
        $this->value = $value;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getChampFixe(): ?string {
        return $this->champFixe;
    }

    public function setChampFixe(?string $champFixe): self {
        $this->champFixe = $champFixe;

        return $this;
    }

}
