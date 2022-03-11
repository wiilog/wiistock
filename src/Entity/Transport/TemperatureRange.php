<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TemperatureRangeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TemperatureRangeRepository::class)]
class TemperatureRange {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $value = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getValue(): ?string {
        return $this->value;
    }

    public function setValue(?string $value): void {
        $this->value = $value;
    }

}
