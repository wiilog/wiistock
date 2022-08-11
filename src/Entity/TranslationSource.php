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
use Doctrine\ORM\Mapping\OneToOne;

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

    #[OneToOne(mappedBy: "labelTranslation", targetEntity: Type::class)]
    private ?Type $type = null;

    #[OneToOne(mappedBy: "labelTranslation", targetEntity: Nature::class)]
    private ?Nature $nature = null;

    #[OneToOne(mappedBy: "labelTranslation", targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[OneToOne(mappedBy: "labelTranslation", targetEntity: FreeField::class)]
    private ?FreeField $freeField = null;

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

    public function getTranslationIn(string $slug): ?Translation {
        return $this->getTranslations()
            ->filter(fn(Translation $translation) => $translation->getLanguage()->getSlug() === $slug)
            ->first() ?: null;
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

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type->getEntity() !== $this) {
            $oldType = $this->type;
            $this->type = null;
            $oldType->setEntity(null);
        }
        $this->type = $type;
        if($this->type && $this->type->getEntity() !== $this) {
            $this->type->setEntity($this);
        }

        return $this;
    }

    public function getNature(): ?Nature {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self {
        if($this->nature && $this->nature->getEntity() !== $this) {
            $oldNature = $this->nature;
            $this->nature = null;
            $oldNature->setEntity(null);
        }
        $this->nature = $nature;
        if($this->nature && $this->nature->getEntity() !== $this) {
            $this->nature->setEntity($this);
        }

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        if($this->status && $this->status->getEntity() !== $this) {
            $oldStatus = $this->status;
            $this->status = null;
            $oldStatus->setEntity(null);
        }
        $this->status = $status;
        if($this->status && $this->status->getEntity() !== $this) {
            $this->status->setEntity($this);
        }

        return $this;
    }

    public function getFreeField(): ?FreeField {
        return $this->freeField;
    }

    public function setFreeField(?FreeField $freeField): self {
        if($this->freeField && $this->freeField->getEntity() !== $this) {
            $oldFreeField = $this->freeField;
            $this->freeField = null;
            $oldFreeField->setEntity(null);
        }
        $this->freeField = $freeField;
        if($this->freeField && $this->freeField->getEntity() !== $this) {
            $this->freeField->setEntity($this);
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
