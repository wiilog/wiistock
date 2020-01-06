<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParametrageGlobalRepository")
 */
class ParametrageGlobal
{
	const CREATE_DL_AFTER_RECEPTION = 'CREATION DL APRES RECEPTION';
	const CREATE_PREPA_AFTER_DL = 'CREATION PREPA APRES DL';
	const INCLUDE_BL_IN_LABEL = 'INCLURE BL SUR ETIQUETTE';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $parametre;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParametre(): ?bool
    {
        return $this->parametre;
    }

    public function setParametre(?bool $parametre): self
    {
        $this->parametre = $parametre;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }
}
