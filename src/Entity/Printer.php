<?php

namespace App\Entity;

use App\Repository\PrinterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\OneToMany(mappedBy: "defaultPrinter", targetEntity: Utilisateur::class)]
    private Collection $defaultUsers;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: "allowedPrinters")]
    private Collection $allowedUsers;

    public function __construct() {
        $this->defaultUsers = new ArrayCollection();
        $this->allowedUsers = new ArrayCollection();
    }

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

    public function getDefaultUsers(): Collection {
        return $this->defaultUsers;
    }

    public function addDefaultUser(Utilisateur $defaultUser): self {
        if(!$this->defaultUsers->contains($defaultUser)) {
            $this->defaultUsers[] = $defaultUser;
            $defaultUser->setDefaultPrinter($this);
        }

        return $this;
    }

    public function removeDefaultUser(Utilisateur $defaultUser): self {
        if($this->defaultUsers->removeElement($defaultUser)) {
            if($defaultUser->getDefaultPrinter() === $this) {
                $defaultUser->setDefaultPrinter(null);
            }
        }

        return $this;
    }

    public function setDefaultUsers(?array $defaultUsers): self {
        foreach($this->getDefaultUsers()->toArray() as $defaultUser) {
            $this->removeDefaultUser($defaultUser);
        }

        $this->defaultUsers = new ArrayCollection();
        foreach($defaultUsers as $defaultUser) {
            $this->addDefaultUser($defaultUser);
        }

        return $this;
    }

    public function getAllowedUsers(): Collection {
        return $this->allowedUsers;
    }

    public function addAllowedUser(Utilisateur $allowedUser): self {
        if(!$this->allowedUsers->contains($allowedUser)) {
            $this->allowedUsers[] = $allowedUser;
            $allowedUser->addAllowedPrinter($this);
        }

        return $this;
    }

    public function removeAllowedUser(Utilisateur $allowedUser): self {
        if($this->allowedUsers->removeElement($allowedUser)) {
            $allowedUser->removeAllowedPrinter($this);
        }

        return $this;
    }

    public function setAllowedUsers(?array $allowedUsers): self {
        foreach($this->getAllowedUsers()->toArray() as $allowedUser) {
            $this->removeAllowedUser($allowedUser);
        }

        $this->allowedUsers = new ArrayCollection();
        foreach($allowedUsers as $allowedUser) {
            $this->addAllowedUser($allowedUser);
        }

        return $this;
    }
}
