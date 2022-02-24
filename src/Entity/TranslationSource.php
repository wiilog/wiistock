<?php

namespace App\Entity;

use App\Repository\TranslationSourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity(TranslationSourceRepository::class)]
class TranslationSource {

    #[Id]
    #[GeneratedValue]
    #[Column(type: "integer")]
    private ?int $id = null;

    #[ManyToOne(targetEntity: TranslationCategory::class, inversedBy: "translationSources")]
    private ?TranslationCategory $category = null;

    #[Column(type: "text", nullable: true)]
    private ?string $tooltip = null;

    #[OneToMany(mappedBy: "source", targetEntity: Translation::class)]
    private Collection $translations;

    #[ManyToOne(targetEntity: FreeField::class, inversedBy: "elementsTranslations")]
    private ?FreeField $elementOfFreeField = null;

    public function __construct() {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getCategory(): ?TranslationCategory {
        return $this->category;
    }

    public function setCategory(?TranslationCategory $category): self {
        if($this->category && $this->category !== $category) {
            $this->category->removeTranslationSource($this);
        }
        $this->category = $category;
        $category?->addTranslationSource($this);

        return $this;
    }

    public function getTooltip(): ?string {
        return $this->tooltip;
    }

    public function setTooltip(?string $tooltip): self {
        $this->tooltip = $tooltip;

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
            $translation->setSource($this);
        }

        return $this;
    }

    public function removeTranslation(Translation $translation): self {
        if($this->translations->removeElement($translation)) {
            if($translation->getSource() === $this) {
                $translation->setSource(null);
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

    public function getElementOfFreeField(): ?FreeField {
        return $this->elementOfFreeField;
    }

    public function setElementOfFreeField(?FreeField $elementOfFreeField): self {
        if($this->elementOfFreeField && $this->elementOfFreeField !== $elementOfFreeField) {
            $this->elementOfFreeField->removeElementTranslation($this);
        }
        $this->elementOfFreeField = $elementOfFreeField;
        $elementOfFreeField?->addElementTranslation($this);

        return $this;
    }

}
