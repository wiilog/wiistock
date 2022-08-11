<?php

namespace App\Entity;

use App\Repository\LanguageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity(LanguageRepository::class)]
class Language {

    public const DATE_FORMATS = [
        'jj/mm/aaaa'=>'d/m/Y',
        'mm-dd-yyyy'=>'m-d-Y',
        'yyyy-mm-dd'=>'Y-m-d'
    ];

    #[Id]
    #[GeneratedValue]
    #[Column(type: "integer")]
    private ?int $id = null;

    #[Column(type: "string", length: 255)]
    private ?string $label = null;

    #[Column(type: "string", length: 255)]
    private ?string $slug = null;

    #[Column(type: "string", length: 255)]
    private ?string $flag = null;

    #[Column(type: "boolean")]
    private ?bool $selected = null;

    #[Column(type: "boolean")]
    private ?bool $selectable = null;

    #[OneToMany(mappedBy: "language", targetEntity: Translation::class)]
    private Collection $translations;

    public function __construct() {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getSlug(): ?string {
        return $this->slug;
    }

    public function setSlug(string $slug): self {
        $this->slug = $slug;

        return $this;
    }

    public function getFlag(): ?string {
        return $this->flag;
    }

    public function setFlag(string $flag): self {
        $this->flag = $flag;

        return $this;
    }

    public function getSelected(): ?bool {
        return $this->selected;
    }

    public function setSelected(bool $selected): self {
        $this->selected = $selected;

        return $this;
    }

    public function getSelectable(): ?bool {
        return $this->selectable;
    }

    public function setSelectable(bool $selectable): self {
        $this->selectable = $selectable;

        return $this;
    }

    /**
     * @return Collection<int, Translation>
     */
    public function getTranslations(): Collection {
        return $this->translations;
    }

    public function addTranslation(Translation $translation): self {
        if(!$this->translations->contains($translation)) {
            $this->translations[] = $translation;
            $translation->setLanguage($this);
        }

        return $this;
    }

    public function removeTranslation(Translation $translation): self {
        if($this->translations->removeElement($translation)) {
            if($translation->getLanguage() === $this) {
                $translation->setLanguage(null);
            }
        }

        return $this;
    }

    public function setTranslations(?array $translations): self {
        foreach($this->getTranslations()->toArray() as $translation) {
            $this->removeTranslation($translation);
        }

        $this->translations = new ArrayCollection();
        foreach($translations as $translation) {
            $this->addTranslation($translation);
        }

        return $this;
    }

}
