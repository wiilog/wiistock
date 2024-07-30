<?php

namespace App\Entity\FreeField;

use App\Entity\CategorieCL;
use App\Entity\FiltreRef;
use App\Entity\Interfaces\Serializable;
use App\Entity\Language;
use App\Entity\TranslationSource;
use App\Helper\LanguageHelper;
use App\Repository\FreeField\FreeFieldRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: FreeFieldRepository::class)]
class FreeField implements Serializable {

    const TYPE_BOOL = 'booleen';
    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_LIST = 'list';
    const TYPE_LIST_MULTIPLE = 'list multiple';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';

    const SPECIC_COLLINS_BL = 'BL';
    const MACHINE_PDT_FREE_FIELD = 'Machine PDT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Attribute used for data warehouse, do not delete it
     */
    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $typage = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $defaultValue = null;

    #[ORM\OneToMany(mappedBy: 'champLibre', targetEntity: FiltreRef::class)]
    private Collection $filters;

    /**
     * Attribute used for data warehouse, do not delete it
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $elements = [];

    #[ORM\ManyToOne(targetEntity: CategorieCL::class)]
    private ?CategorieCL $categorieCL = null;

    #[ORM\OneToOne(mappedBy: "freeField", targetEntity: TranslationSource::class)]
    private ?TranslationSource $labelTranslation = null;

    #[ORM\OneToOne(mappedBy: "freeFieldDefaultValue", targetEntity: TranslationSource::class)]
    private ?TranslationSource $defaultValueTranslation = null;

    #[ORM\OneToMany(mappedBy: "elementOfFreeField", targetEntity: TranslationSource::class)]
    private Collection $elementsTranslations;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $maxCharactersLength = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $minCharactersLength = null;

    /**
     * @var Collection<int, FreeFieldManagementRule>
     */
    #[ORM\OneToMany(mappedBy: 'freeField', targetEntity: FreeFieldManagementRule::class, orphanRemoval: true)]
    private Collection $freeFieldManagementRules;

    public function __construct() {
        $this->filters = new ArrayCollection();
        $this->elementsTranslations = new ArrayCollection();
        $this->freeFieldManagementRules = new ArrayCollection();
    }

    public function __toString(): string {
        return $this->label;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabelIn(Language|string $in,
                               Language|string|null $default = null): ?string {
        $in = LanguageHelper::clearLanguage($in);
        $default = LanguageHelper::clearLanguage($default);

        return $this->getLabelTranslation()
            ?->getTranslationIn($in, $default)
            ?->getTranslation()
            ?: $this->getLabel();
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getTypage(): ?string {
        return $this->typage;
    }

    public function setTypage(?string $typage): self {
        $this->typage = $typage;

        return $this;
    }

    public function getDefaultValueIn(Language|string $in, Language|string|null $default = null): ?string {
        $in = LanguageHelper::clearLanguage($in);
        $default = LanguageHelper::clearLanguage($default);
        return $this->getDefaultValueTranslation()
            ?->getTranslationIn($in, $default)
            ?->getTranslation();
    }

    public function getDefaultValue(): ?string {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): self {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * @return Collection|FiltreRef[]
     */
    public function getFilters(): Collection {
        return $this->filters;
    }

    public function addFilter(FiltreRef $filter): self {
        if(!$this->filters->contains($filter)) {
            $this->filters[] = $filter;
            $filter->setChampLibre($this);
        }

        return $this;
    }

    public function removeFilter(FiltreRef $filter): self {
        if($this->filters->contains($filter)) {
            $this->filters->removeElement($filter);
            // set the owning side to null (unless already changed)
            if($filter->getChampLibre() === $this) {
                $filter->setChampLibre(null);
            }
        }

        return $this;
    }

    public function getElementsIn(Language|string $in, Language|string|null $default = null): array {
        $in = LanguageHelper::clearLanguage($in);
        $default = LanguageHelper::clearLanguage($default);

        return Stream::from($this->getElementsTranslations())
            ->map(fn(TranslationSource $source) => $source->getTranslationIn($in, $default)?->getTranslation())
            ->filter()
            ->toArray();
    }

    public function getElements(): array {
        return $this->elements ?: [];
    }

    public function setElements(?array $elements): self {
        $this->elements = $elements;

        return $this;
    }

    public function getCategorieCL(): ?CategorieCL {
        return $this->categorieCL;
    }

    public function setCategorieCL(?CategorieCL $categorieCL): self {
        $this->categorieCL = $categorieCL;

        return $this;
    }

    public function getLabelTranslation(): ?TranslationSource {
        return $this->labelTranslation;
    }

    public function setLabelTranslation(?TranslationSource $labelTranslation): self {
        if($this->labelTranslation && $this->labelTranslation->getFreeField() !== $this) {
            $oldLabelTranslation = $this->labelTranslation;
            $this->labelTranslation = null;
            $oldLabelTranslation->setFreeField(null);
        }
        $this->labelTranslation = $labelTranslation;
        if($this->labelTranslation && $this->labelTranslation->getFreeField() !== $this) {
            $this->labelTranslation->setFreeField($this);
        }

        return $this;
    }

    public function getDefaultValueTranslation(): ?TranslationSource {
        return $this->defaultValueTranslation;
    }

    public function setDefaultValueTranslation(?TranslationSource $defaultValueTranslation): self {
        if($this->defaultValueTranslation && $this->defaultValueTranslation->getFreeFieldDefaultValue() !== $this) {
            $oldDefaultValueTranslation = $this->defaultValueTranslation;
            $this->defaultValueTranslation = null;
            $oldDefaultValueTranslation->setFreeFieldDefaultValue(null);
        }
        $this->defaultValueTranslation = $defaultValueTranslation;
        if($this->defaultValueTranslation && $this->defaultValueTranslation->getFreeFieldDefaultValue() !== $this) {
            $this->defaultValueTranslation->setFreeFieldDefaultValue($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TranslationSource>
     */
    public function getElementsTranslations(): Collection {
        return $this->elementsTranslations;
    }

    public function getElementTranslation(string $element): ?TranslationSource {
        return $this->getElementsTranslations()
                ->filter(fn(TranslationSource $source) => $source->getTranslationIn(Language::FRENCH_SLUG)->getTranslation() === $element)
                ->first() ?: null;
    }

    public function addElementTranslation(TranslationSource $elementTranslation): self {
        if(!$this->elementsTranslations->contains($elementTranslation)) {
            $this->elementsTranslations[] = $elementTranslation;
            $elementTranslation->setElementOfFreeField($this);
        }

        return $this;
    }

    public function removeElementTranslation(TranslationSource $elementTranslation): self {
        if($this->elementsTranslations->removeElement($elementTranslation)) {
            if($elementTranslation->getElementOfFreeField() === $this) {
                $elementTranslation->setElementOfFreeField(null);
            }
        }

        return $this;
    }

    public function setElementTranslations(?array $elementTranslations): self {
        foreach($this->getElementsTranslations()->toArray() as $elementTranslation) {
            $this->removeElementTranslation($elementTranslation);
        }

        $this->elementsTranslations = new ArrayCollection();
        foreach($elementTranslations as $elementTranslation) {
            $this->addElementTranslation($elementTranslation);
        }

        return $this;
    }

    public function getMinCharactersLength(): ?int {
        return $this->minCharactersLength;
    }

    public function setMinCharactersLength(?int $minCharactersLength): self {
        $this->minCharactersLength = $minCharactersLength;

        return $this;
    }

    public function getMaxCharactersLength(): ?int {
        return $this->maxCharactersLength;
    }

    public function setMaxCharactersLength(?int $maxCharactersLength): self {
        $this->maxCharactersLength = $maxCharactersLength;

        return $this;
    }

    public function serialize(): array {
        return [
            'id' => $this->getId(),
            'label' => $this->getLabel(),
            'elements' => $this->getElements(),
            'typing' => $this->getTypage(),
            'defaultValue' => $this->getDefaultValue(),
        ];
    }

    /**
     * @return Collection<int, FreeFieldManagementRule>
     */
    public function getFreeFieldManagementRules(): Collection
    {
        return $this->freeFieldManagementRules;
    }

    public function addFreeFieldManagementRule(FreeFieldManagementRule $freeFieldManagementRule): static
    {
        if (!$this->freeFieldManagementRules->contains($freeFieldManagementRule)) {
            $this->freeFieldManagementRules->add($freeFieldManagementRule);
            $freeFieldManagementRule->setFreeField($this);
        }

        return $this;
    }

    public function removeFreeFieldManagementRule(FreeFieldManagementRule $freeFieldManagementRule): static
    {
        if ($this->freeFieldManagementRules->removeElement($freeFieldManagementRule)) {
            // set the owning side to null (unless already changed)
            if ($freeFieldManagementRule->getFreeField() === $this) {
                $freeFieldManagementRule->setFreeField(null);
            }
        }

        return $this;
    }

}
