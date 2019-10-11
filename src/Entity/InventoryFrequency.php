<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InventoryFrequencyRepository")
 */
class InventoryFrequency
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
    private $label;

	/**
	 * @ORM\Column(type="integer")
	 */
    private $nbMonths;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getNbMonths(): ?int
    {
        return $this->nbMonths;
    }

    public function setNbMonths(int $nbMonths): self
    {
        $this->nbMonths = $nbMonths;

        return $this;
    }

}
