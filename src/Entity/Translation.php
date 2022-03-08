<?php

namespace App\Entity;

use App\Repository\TranslationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TranslationRepository::class)]
class Translation {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 64, nullable: true)]
    private ?string $menu = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $translation = null;

    #[ORM\Column(type: "boolean", nullable: true)]
    private ?bool $updated = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getMenu(): ?string {
        return $this->menu;
    }

    public function setMenu(?string $menu): self {
        $this->menu = $menu;

        return $this;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getTranslation(): ?string {
        return $this->translation;
    }

    public function setTranslation(?string $translation): self {
        $this->translation = $translation;

        return $this;
    }

    public function getUpdated(): ?bool {
        return $this->updated;
    }

    public function setUpdated(?bool $updated): self {
        $this->updated = $updated;

        return $this;
    }

}
