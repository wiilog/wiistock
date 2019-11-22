<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DaysWorkedRepository")
 */
class DaysWorked
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
    private $day;

    /**
     * @ORM\Column(type="boolean")
     */
    private $worked;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $times;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $displayOrder;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorked(): ?bool
    {
        return $this->worked;
    }

    public function setWorked(bool $worked): self
    {
        $this->worked = $worked;

        return $this;
    }

    public function getTimes(): ?string
    {
        return $this->times;
    }

    public function setTimes(?string $times): self
    {
        $this->times = $times;

        return $this;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(?string $day): self
    {
        $this->day = $day;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }
}
