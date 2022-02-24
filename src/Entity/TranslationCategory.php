<?php

namespace App\Entity;

use App\Repository\TranslationCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity(TranslationCategoryRepository::class)]
class TranslationCategory {

    #[Id]
    #[GeneratedValue]
    #[Column(type: "integer")]
    private ?int $id = null;

    #[ManyToOne(targetEntity: TranslationCategory::class, inversedBy: "children")]
    private ?TranslationCategory $parent = null;

    #[OneToMany(mappedBy: "parent", targetEntity: TranslationCategory::class)]
    private Collection $children;

    #[Column(type: "integer")]
    private ?int $type = null;

    #[Column(type: "string", length: 255)]
    private ?string $label = null;

    #[OneToMany(mappedBy: "category", targetEntity: TranslationSource::class)]
    private Collection $translationSources;

    public function __construct() {
        $this->children = new ArrayCollection();
        $this->translationSources = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getParent(): ?TranslationCategory {
        return $this->parent;
    }

    public function setParent(?TranslationCategory $parent): self {
        if($this->parent && $this->parent !== $parent) {
            $this->parent->removeChild($this);
        }

        $this->parent = $parent;
        $parent?->addChild($this);

        return $this;
    }

    /**
     * @return Collection<int, TranslationCategory>
     */
    public function getChildren(): Collection {
        return $this->children;
    }

    public function addChild(TranslationCategory $child): self {
        if(!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(TranslationCategory $child): self {
        if($this->children->removeElement($child)) {
            if($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function setChildren(?array $children): self {
        foreach($this->getChildren()->toArray() as $child) {
            $this->removeChild($child);
        }

        $this->children = new ArrayCollection();
        foreach($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    public function getType(): ?int {
        return $this->type;
    }

    public function setType(int $type): self {
        $this->type = $type;

        return $this;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection<int, TranslationSource>
     */
    public function getTranslationSources(): Collection {
        return $this->translationSources;
    }

    public function addTranslationSource(TranslationSource $translationSource): self {
        if(!$this->translationSources->contains($translationSource)) {
            $this->translationSources[] = $translationSource;
            $translationSource->setCategory($this);
        }

        return $this;
    }

    public function removeTranslationSource(TranslationSource $translationSource): self {
        if($this->translationSources->removeElement($translationSource)) {
            if($translationSource->getCategory() === $this) {
                $translationSource->setCategory(null);
            }
        }

        return $this;
    }

    public function setTranslationSources(?array $translationSources): self {
        foreach($this->getTranslationSources()->toArray() as $translationSource) {
            $this->removeTranslationSource($translationSource);
        }

        $this->translationSources = new ArrayCollection();
        foreach($translationSources as $translationSource) {
            $this->addTranslationSource($translationSource);
        }

        return $this;
    }

}
