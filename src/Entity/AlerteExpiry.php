<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AlerteExpiryRepository")
 */
class AlerteExpiry
{
	const TYPE_PERIOD_DAY = 'jour';
	const TYPE_PERIOD_WEEK = 'semaine';
	const TYPE_PERIOD_MONTH = 'mois';
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $numero;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="alertesStock")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="alertesStock")
     */
    private $refArticle;

	/**
	 * @ORM\Column(type="integer")
	 */
    private $nbPeriod;

	/**
	 * @ORM\Column(type="string")
	 */
    private $typePeriod;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $activated;


    public function getId(): ?int
    {
        return $this->id;
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

    public function getNbPeriod(): ?int
    {
        return $this->nbPeriod;
    }

    public function setNbPeriod(?int $nbPeriod): self
    {
        $this->nbPeriod = $nbPeriod;

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

    public function getTypePeriod(): ?string
    {
        return $this->typePeriod;
    }

    public function setTypePeriod(string $typePeriod): self
    {
        $this->typePeriod = $typePeriod;

        return $this;
    }

}
