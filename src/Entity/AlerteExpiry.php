<?php

namespace App\Entity;

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
	 * @ORM\Column(type="integer")
	 */
    private $nbPeriod;

	/**
	 * @ORM\Column(type="string")
	 */
    private $typePeriod;


    public function getId(): ?int
    {
        return $this->id;
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
