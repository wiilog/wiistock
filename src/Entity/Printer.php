<?php

namespace App\Entity;

use App\Repository\PrinterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrinterRepository::class)]
class Printer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $address = null;

    #[ORM\Column(type: Types::FLOAT, nullable: false)]
    private ?float $width = null;

    #[ORM\Column(type: Types::FLOAT, nullable: false)]
    private ?float $height = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private ?int $dpi = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function setWidth(float $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setHeight(float $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getDpi(): ?int
    {
        return $this->dpi;
    }

    public function setDpi(int $dpi): static
    {
        $this->dpi = $dpi;

        return $this;
    }
}
