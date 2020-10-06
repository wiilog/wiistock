<?php

namespace App\Entity;

use App\Repository\AverageRequestTimeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AverageRequestTimeRepository::class)
 */
class AverageRequestTime
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity=Type::class, inversedBy="averageRequestTime", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $type;

    /**
     * @ORM\Column(type="dateinterval", nullable=true)
     */
    private $average;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $total;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAverage(): ?\DateInterval
    {
        return $this->average;
    }

    public function setAverage(?\DateInterval $average): self
    {
        $this->average = $average;

        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(?int $total): self
    {
        $this->total = $total;

        return $this;
    }
}
