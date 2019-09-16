<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParamInventoryRepository")
 */
class ParamInventory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $nbMonthsPeriod;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNbMonthsPeriod(): ?int
    {
        return $this->nbMonthsPeriod;
    }

    public function setNbMonthsPeriod(int $nbMonthsPeriod): self
    {
        $this->nbMonthsPeriod = $nbMonthsPeriod;

        return $this;
    }
}
