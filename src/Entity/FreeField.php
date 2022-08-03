<?php

namespace App\Entity;

use App\Entity\Interfaces\Serializable;
use App\Repository\FreeFieldRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FreeFieldRepository::class)]
class FreeField implements Serializable {

    const TYPE_BOOL = 'booleen';
    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_LIST = 'list';
    const TYPE_LIST_MULTIPLE = 'list multiple';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPAGE = [
        [
            'value' => FreeField::TYPE_BOOL,
            'label' => 'Oui/Non',
        ],
        [
            'value' => FreeField::TYPE_DATE,
            'label' => 'Date',
        ],
        [
            'value' => FreeField::TYPE_DATETIME,
            'label' => 'Date et heure',
        ],
        [
            'value' => FreeField::TYPE_LIST,
            'label' => 'Liste',
        ],
        [
            'value' => FreeField::TYPE_LIST_MULTIPLE,
            'label' => 'Liste multiple',
        ],
        [
            'value' => FreeField::TYPE_NUMBER,
            'label' => 'Nombre',
        ],
        [
            'value' => FreeField::TYPE_TEXT,
            'label' => 'Texte',
        ],
    ];
    const TYPAGE_ARR = [
        FreeField::TYPE_BOOL => 'Oui/Non',
        FreeField::TYPE_DATE => 'Date',
        FreeField::TYPE_DATETIME => 'Date et heure',
        FreeField::TYPE_LIST => 'Liste',
        FreeField::TYPE_NUMBER => 'Nombre',
        FreeField::TYPE_TEXT => 'Texte',
        FreeField::TYPE_LIST_MULTIPLE => 'Liste multiple',
    ];
    const SPECIC_COLLINS_BL = 'BL';
    const MACHINE_PDT_FREE_FIELD = 'Machine PDT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private $label;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'champsLibres')]
    private $type;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $typage;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $defaultValue;

    #[ORM\OneToMany(targetEntity: FiltreRef::class, mappedBy: 'champLibre')]
    private $filters;

    #[ORM\Column(type: 'json', nullable: true)]
    private $elements = [];

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $requiredCreate;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $requiredEdit;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 1])]
    private $displayedCreate;

    #[ORM\ManyToOne(targetEntity: CategorieCL::class, inversedBy: 'champsLibres')]
    private ?CategorieCL $categorieCL = null;

    #[ORM\OneToOne(targetEntity: TranslationSource::class, inversedBy: "freeField")]
    private ?TranslationSource $labelTranslation = null;

    #[OneToMany(mappedBy: "elementOfFreeField", targetEntity: TranslationSource::class)]
    private Collection $elementsTranslations;

    public function __construct() {
        $this->filters = new ArrayCollection();
        $this->elementsTranslations = new ArrayCollection();
    }

    public function __toString(): string {
        return $this->label;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getTypage(): ?string {
        return $this->typage;
    }

    public function setTypage(?string $typage): self {
        $this->typage = $typage;

        return $this;
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

    public function getElements(): ?array {
        return $this->elements ?: [];
    }

    public function setElements(?array $elements): self {
        $this->elements = $elements;

        return $this;
    }

    public function isRequiredCreate(): ?bool {
        return $this->requiredCreate;
    }

    public function setRequiredCreate(?bool $requiredCreate): self {
        $this->requiredCreate = $requiredCreate;

        return $this;
    }

    public function isRequiredEdit(): ?bool {
        return $this->requiredEdit;
    }

    public function setRequiredEdit(?bool $requiredEdit): self {
        $this->requiredEdit = $requiredEdit;

        return $this;
    }

    public function getDisplayedCreate(): ?bool {
        return $this->displayedCreate;
    }

    public function setDisplayedCreate(?bool $displayedCreate): self {
        $this->displayedCreate = $displayedCreate;
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

    /**
     * @return Collection<int, TranslationSource>
     */
    public function getElementsTranslations(): Collection {
        return $this->elementsTranslations;
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

    public function serialize(): array {
        $type = $this->getType();
        $categoryType = $type ? $type->getCategory() : null;
        return [
            'id' => $this->getId(),
            'label' => $this->getLabel(),
            'elements' => $this->getElements(),
            'typing' => $this->getTypage(),
            'defaultValue' => $this->getDefaultValue(),
            'requiredCreate' => $this->isRequiredCreate(),
            'requiredEdit' => $this->isRequiredEdit(),
            'typeId' => $this->getType() ? $this->getType()->getId() : null,
            'categoryType' => $categoryType ? $categoryType->getLabel() : null,
        ];
    }

}
