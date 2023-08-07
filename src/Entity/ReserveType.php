<?php

namespace App\Entity;

use App\Repository\ReserveTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReserveTypeRepository::class)]
class ReserveType
{
    const DEFAULT_QUALITY_TYPE = 'Réserve qualité';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', unique: true)]
    private ?string $label = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $notifiedUsers;

    #[ORM\Column(type: "boolean", nullable: true)]
    private ?bool $defaultReserveType = null;

    #[ORM\Column(type: "boolean", nullable: true, options: ["default" => true])]
    private ?bool $active = null;

    public function __construct() {
        $this->notifiedUsers = new ArrayCollection();
    }

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

    /**
     * @return Collection|Utilisateur[]
     */
    public function getNotifiedUsers(): Collection {
        return $this->notifiedUsers;
    }

    public function addNotifiedUser(Utilisateur $notifiedUser): self {
        if(!$this->notifiedUsers->contains($notifiedUser)) {
            $this->notifiedUsers[] = $notifiedUser;
        }

        return $this;
    }

    public function removeNotifiedUser(Utilisateur $notifiedUser): self {
        if($this->notifiedUsers->contains($notifiedUser)) {
            $this->notifiedUsers->removeElement($notifiedUser);
        }

        return $this;
    }

    public function setNotifiedUsers(?iterable $notifiedUsers): self {
        foreach($this->getNotifiedUsers()->toArray() as $notifiedUser) {
            $this->removeNotifiedUser($notifiedUser);
        }

        $this->notifiedUsers = new ArrayCollection();
        foreach($notifiedUsers ?? [] as $notifiedUser) {
            $this->addNotifiedUser($notifiedUser);
        }

        return $this;
    }

    public function isDefaultReserveType(): ?bool
    {
        return $this->defaultReserveType;
    }

    public function setDefaultReserveType(?bool $defaultReserveType): self
    {
        $this->defaultReserveType = $defaultReserveType;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;

        return $this;
    }
}
