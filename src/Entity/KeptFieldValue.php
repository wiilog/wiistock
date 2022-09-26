<?php

namespace App\Entity;

use App\Repository\KeptFieldValueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KeptFieldValueRepository::class)]
class KeptFieldValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $entity = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $field = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $value = null;

    #[ORM\ManyToOne(inversedBy: 'keptFieldValues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        if($this->user && $this->user !== $user) {
            $this->user->removeKeptFieldValue($this);
        }

        $this->user = $user;
        $user?->addKeptFieldValue($this);

        return $this;
    }
}
