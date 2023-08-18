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

    private const DMY_FORMAT = 'd/m/Y';
    private const MDY_FORMAT = 'm-d-Y';
    private const YMD_FORMAT = 'Y-m-d';

    public const DMY_MYSQL_FORMAT = '%d/%m/%Y';
    private const MDY_MYSQL_FORMAT = '%m-%d-%Y';
    private const YMD_MYSQL_FORMAT = '%Y-%m-%d';

    public const DATE_FORMATS = [
        "jj/mm/aaaa" => self::DMY_FORMAT,
        "mm-dd-yyyy" => self::MDY_FORMAT,
        "yyyy-mm-dd" => self::YMD_FORMAT,
    ];

    public const MYSQL_DATE_FORMATS = [
        self::DMY_FORMAT => self::DMY_MYSQL_FORMAT,
        self::MDY_FORMAT => self::MDY_MYSQL_FORMAT,
        self::YMD_FORMAT => self::YMD_MYSQL_FORMAT,
    ];

    public const FRENCH_DEFAULT_SLUG = 'french-default';
    public const ENGLISH_DEFAULT_SLUG = 'english-default';
    public const FRENCH_SLUG = 'french';
    public const ENGLISH_SLUG = 'english';
    public const NEW_SLUG = 'NEW';

    public const PREVIOUS_TRANSLATIONS_SYSTEM_SLUG = self::FRENCH_SLUG;
    public const DEFAULT_LANGUAGE_SLUG = self::FRENCH_DEFAULT_SLUG;

    public const DEFAULT_LANGUAGE_TRANSLATIONS = [
        self::FRENCH_SLUG => self::FRENCH_DEFAULT_SLUG,
        self::ENGLISH_SLUG => self::ENGLISH_DEFAULT_SLUG
    ];

    public const NOT_DELETABLE_LANGUAGES = [
        self::FRENCH_DEFAULT_SLUG,
        self::ENGLISH_DEFAULT_SLUG,
        self::FRENCH_SLUG,
        self::ENGLISH_SLUG,
        self::NEW_SLUG
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

    #[Column(type: 'boolean', options: ['default' => false])]
    private bool $hidden = false;

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

    public function serialize(?Utilisateur $user = null): array {
        return [
            'label' => $this->getLabel(),
            'value' => $this->getId(),
            'slug' => $this->getSlug(),
            'iconUrl' => $this->getFlag(),
            'checked' => $user && $user->getLanguage()->getId() === $this->getId()
        ];
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
    }
}
