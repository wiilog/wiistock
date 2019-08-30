<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AlerteRepository")
 */
class Alerte
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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $numero;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $limitSecurity;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $limitAlert;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="alertesStock")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="alertesStock")
     */
    private $refArticle;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $activated;


    public function getId(): ?int
    {
        return $this->id;
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

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    public function getLimitSecurity(): ?int
    {
        return $this->limitSecurity;
    }

    public function setLimitSecurity(?int $limitSecurity): self
    {
        $this->limitSecurity = $limitSecurity;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getRefArticle(): ?ReferenceArticle
    {
        return $this->refArticle;
    }

    public function setRefArticle(?ReferenceArticle $refArticle): self
    {
        $this->refArticle = $refArticle;

        return $this;
    }

    public function getActivated(): ?bool
    {
        return $this->activated;
    }

    public function setActivated(?bool $activated): self
    {
        $this->activated = $activated;

        return $this;
    }

    public function __toString()
    {
        return $this->label;
    }

    public function getLimitAlert(): ?int
    {
        return $this->limitAlert;
    }

    public function setLimitAlert(?int $limitAlert): self
    {
        $this->limitAlert = $limitAlert;

        return $this;
    }

}
