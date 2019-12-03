<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArrivalHistoryRepository")
 */
class ArrivalHistory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $numberOfArrivals;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $conformRate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $day;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumberOfArrivals(): ?int
    {
        return $this->numberOfArrivals;
    }

    public function setNumberOfArrivals(?int $numberOfArrivals): self
    {
        $this->numberOfArrivals = $numberOfArrivals;

        return $this;
    }

    public function getConformRate(): ?int
    {
        return $this->conformRate;
    }

    public function setConformRate(?int $conformRate): self
    {
        $this->conformRate = $conformRate;

        return $this;
    }

    public function getDay(): ?\DateTimeInterface
    {
        return $this->day;
    }

    public function setDay(\DateTimeInterface $day): self
    {
        $this->day = $day;

        return $this;
    }
}
