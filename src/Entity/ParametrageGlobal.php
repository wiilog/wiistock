<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParametrageGlobalRepository")
 */
class ParametrageGlobal
{
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
