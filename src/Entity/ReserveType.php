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

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $emails = [];

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $notifiedUsers;

    #[ORM\Column(type: "boolean", nullable: true)]
    private ?bool $isDefault = null;

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

    public function isDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setDefault(?bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }
}
