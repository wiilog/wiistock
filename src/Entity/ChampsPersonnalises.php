<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChampsPersonnalisesRepository")
 */
class ChampsPersonnalises
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
     * @ORM\Column(type="json")
     */
    private $type;

    /**
     * @ORM\Column(type="boolean")
     */
    private $unicite;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $valeur_defaut;

    /**
     * @ORM\Column(type="boolean")
     */
    private $nullable;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $entite_cible;

    public function getId()
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

    public function getType()
    {
        return $this->type;
    }

    public function setType($type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getUnicite(): ?bool
    {
        return $this->unicite;
    }

    public function setUnicite(bool $unicite): self
    {
        $this->unicite = $unicite;

        return $this;
    }

    public function getValeurDefaut(): ?string
    {
        return $this->valeur_defaut;
    }

    public function setValeurDefaut(?string $valeur_defaut): self
    {
        $this->valeur_defaut = $valeur_defaut;

        return $this;
    }

    public function getNullable(): ?bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $nullable): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function getEntiteCible(): ?string
    {
        return $this->entite_cible;
    }

    public function setEntiteCible(string $entite_cible): self
    {
        $this->entite_cible = $entite_cible;

        return $this;
    }
}
