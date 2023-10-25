<?php

namespace App\Entity;

use App\Repository\PrinterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrinterRepository::class)]
class Printer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false, type: 'string')]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: false, type: 'string')]
    private ?string $address = null;

    #[ORM\Column(nullable: false, type: 'float')]
    private ?float $width = null;

    #[ORM\Column(nullable: false, type: 'float')]
    private ?float $height = null;

    #[ORM\Column(nullable: false, type: 'integer')]
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
