<?php

namespace App\Entity;

use App\Repository\AverageRequestTimeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AverageRequestTimeRepository::class)]
class AverageRequestTime {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\OneToOne(targetEntity: Type::class, inversedBy: 'averageRequestTime', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private $type;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $average;

    public function getId(): ?int {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getAverage(): ?int {
        return $this->average;
    }

    public function setAverage(?int $average): self {
        $this->average = $average;

        return $this;
    }

}
