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

    #[OneToMany(mappedBy: "source", targetEntity: Translation::class, cascade: ["remove"])]
    private Collection $translations;

    #[OneToOne(inversedBy: "labelTranslation", targetEntity: Type::class)]
    private ?Type $type = null;

    #[OneToOne(inversedBy: "labelTranslation", targetEntity: Nature::class)]
    private ?Nature $nature = null;

    #[OneToOne(inversedBy: "labelTranslation", targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[OneToOne(inversedBy: "labelTranslation", targetEntity: FreeField::class)]
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

    public function getTranslationIn(Language|string $in, Language|string|null $default = null): ?Translation {
        if(!$in) {
            throw new \RuntimeException("Input language can not be null");
        }

        if($in instanceof Language) {
            $in = $in->getSlug();
        }

        $translation = $this->getTranslations()
            ->filter(fn(Translation $translation) => $translation->getLanguage()->getSlug() === $in)
            ->first() ?: null;

        if($translation === null && $default) {
            $translation = $this->getTranslationIn($default, null);
        }

        return $translation;
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
        if($this->type && $this->type->getLabelTranslation() !== $this) {
            $oldType = $this->type;
            $this->type = null;
            $oldType->setLabelTranslation(null);
        }
        $this->type = $type;
        if($this->type && $this->type->getLabelTranslation() !== $this) {
            $this->type->setLabelTranslation($this);
        }

        return $this;
    }

    public function getNature(): ?Nature {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self {
        if($this->nature && $this->nature->getLabelTranslation() !== $this) {
            $oldNature = $this->nature;
            $this->nature = null;
            $oldNature->setLabelTranslation(null);
        }
        $this->nature = $nature;
        if($this->nature && $this->nature->getLabelTranslation() !== $this) {
            $this->nature->setLabelTranslation($this);
        }

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        if($this->status && $this->status->getLabelTranslation() !== $this) {
            $oldStatus = $this->status;
            $this->status = null;
            $oldStatus->setLabelTranslation(null);
        }
        $this->status = $status;
        if($this->status && $this->status->getLabelTranslation() !== $this) {
            $this->status->setLabelTranslation($this);
        }

        return $this;
    }

    public function getFreeField(): ?FreeField {
        return $this->freeField;
    }

    public function setFreeField(?FreeField $freeField): self {
        if($this->freeField && $this->freeField->getLabelTranslation() !== $this) {
            $oldFreeField = $this->freeField;
            $this->freeField = null;
            $oldFreeField->setLabelTranslation(null);
        }
        $this->freeField = $freeField;
        if($this->freeField && $this->freeField->getLabelTranslation() !== $this) {
            $this->freeField->setLabelTranslation($this);
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
