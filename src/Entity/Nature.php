<?php

namespace App\Entity;

use App\Entity\Transport\TemperatureRange;
use App\Helper\LanguageHelper;
use App\Repository\NatureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NatureRepository::class)]
class Nature {

    public const ARRIVAL_CODE = 'arrival';
    public const TRANSPORT_COLLECT_CODE = 'transportCollect';
    public const TRANSPORT_DELIVERY_CODE = 'transportDelivery';
    public const DISPATCH_CODE = 'dispatch';

    private const ARRIVAL_LABEL = 'Arrivage';
    private const TRANSPORT_COLLECT_LABEL = 'Transport - Collecte';
    private const TRANSPORT_DELIVERY_LABEL = 'Transport - Livraison';
    private const DISPATCH_LABEL = 'Acheminement';

    public const ENTITIES = [
        self::ARRIVAL_CODE => [
            'label' => self::ARRIVAL_LABEL,
            'showTypes' => false,
        ],
        self::DISPATCH_CODE => [
            'label' => self::DISPATCH_LABEL,
            'showTypes' => false,
        ],
        self::TRANSPORT_COLLECT_CODE => [
            'label' => self::TRANSPORT_COLLECT_LABEL,
            'showTypes' => true,
        ],
        self::TRANSPORT_DELIVERY_CODE => [
            'label' => self::TRANSPORT_DELIVERY_LABEL,
            'showTypes' => true,
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Attribute used for data warehouse, do not delete it
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $code = null;

    #[ORM\OneToMany(mappedBy: 'nature', targetEntity: Pack::class)]
    private Collection $packs;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $defaultQuantity = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $prefix = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $needsMobileSync = null;

    #[ORM\ManyToMany(targetEntity: Emplacement::class, mappedBy: 'allowedNatures')]
    private Collection $emplacements;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 1])]
    private ?bool $displayed = null;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 0])]
    private ?bool $defaultNature = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $displayedOnForms = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $allowedForms = [];

    #[ORM\ManyToMany(targetEntity: TemperatureRange::class, inversedBy: 'natures')]
    #[ORM\JoinTable(name: 'location_temperature_range')]
    private Collection $temperatureRanges;

    #[ORM\OneToOne(mappedBy: "nature", targetEntity: TranslationSource::class)]
    private ?TranslationSource $labelTranslation = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $defaultQuantityForDispatch = null;

    #[ORM\ManyToMany(targetEntity: TagTemplate::class, mappedBy: 'natures')]
    private Collection $tags;

    public function __construct() {
        $this->packs = new ArrayCollection();
        $this->emplacements = new ArrayCollection();
        $this->temperatureRanges = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabelIn(Language|string $in,
                               Language|string|null $default = null): ?string {
        $in = LanguageHelper::clearLanguage($in);
        $default = LanguageHelper::clearLanguage($default);

        $translation = $this->getLabelTranslation();

        return $translation?->getTranslationIn($in, $default)?->getTranslation()
            ?: $this->getLabel()
            ?: '';
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(?string $code): self {
        $this->code = $code;

        return $this;
    }

    /**
     * @return Collection|Pack[]
     */
    public function getPacks(): Collection {
        return $this->packs;
    }

    public function addPack(Pack $pack): self {
        if(!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
            $pack->setNature($this);
        }

        return $this;
    }

    public function removePack(Pack $pack): self {
        if($this->packs->contains($pack)) {
            $this->packs->removeElement($pack);
            // set the owning side to null (unless already changed)
            if($pack->getNature() === $this) {
                $pack->setNature(null);
            }
        }

        return $this;
    }

    public function getDefaultQuantity(): ?int {
        return $this->defaultQuantity;
    }

    public function setDefaultQuantity(?int $defaultQuantity): self {
        $this->defaultQuantity = $defaultQuantity;

        return $this;
    }

    public function getPrefix(): ?string {
        return $this->prefix;
    }

    public function setPrefix(?string $prefix): self {
        $this->prefix = $prefix;

        return $this;
    }

    public function getColor(): ?string {
        return $this->color;
    }

    public function setColor(?string $color): self {
        $this->color = $color;

        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;

        return $this;
    }

    public function getNeedsMobileSync(): ?bool {
        return $this->needsMobileSync;
    }

    public function setNeedsMobileSync(?bool $needsMobileSync): self {
        $this->needsMobileSync = $needsMobileSync;

        return $this;
    }

    public function getDefaultNature(): ?bool {
        return $this->defaultNature;
    }

    public function setDefaultNature(?bool $defaultNature): self {
        $this->defaultNature = $defaultNature;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getEmplacements(): Collection {
        return $this->emplacements;
    }

    public function addEmplacement(Emplacement $emplacement): self {
        if(!$this->emplacements->contains($emplacement)) {
            $this->emplacements[] = $emplacement;
            $emplacement->addAllowedNature($this);
        }

        return $this;
    }

    public function removeEmplacement(Emplacement $emplacement): self {
        if($this->emplacements->contains($emplacement)) {
            $this->emplacements->removeElement($emplacement);
            $emplacement->removeAllowedNature($this);
        }

        return $this;
    }

    public function getDisplayedOnForms(): ?bool
    {
        return $this->displayedOnForms;
    }

    public function setDisplayedOnForms(bool $displayedOnForms): self
    {
        $this->displayedOnForms = $displayedOnForms;

        return $this;
    }

    public function getAllowedForms(): ?array
    {
        return $this->allowedForms;
    }

    public function setAllowedForms(?array $allowedForms): self
    {
        $this->allowedForms = $allowedForms;

        return $this;
    }

    /**
     * @return Collection<int, TemperatureRange>
     */
    public function getTemperatureRanges(): Collection
    {
        return $this->temperatureRanges;
    }

    public function addTemperatureRange(TemperatureRange $temperatureRange): self {
        if (!$this->temperatureRanges->contains($temperatureRange)) {
            $this->temperatureRanges[] = $temperatureRange;
            $temperatureRange->addNature($this);
        }

        return $this;
    }

    public function removeTemperatureRange(TemperatureRange $temperatureRange): self {
        if ($this->temperatureRanges->removeElement($temperatureRange)) {
            $temperatureRange->removeNature($this);
        }

        return $this;
    }

    public function getLabelTranslation(): ?TranslationSource {
        return $this->labelTranslation;
    }

    public function setLabelTranslation(?TranslationSource $labelTranslation): self {
        if($this->labelTranslation && $this->labelTranslation->getNature() !== $this) {
            $oldLabelTranslation = $this->labelTranslation;
            $this->labelTranslation = null;
            $oldLabelTranslation->setNature(null);
        }
        $this->labelTranslation = $labelTranslation;
        if($this->labelTranslation && $this->labelTranslation->getNature() !== $this) {
            $this->labelTranslation->setNature($this);
        }

        return $this;
    }

    public function getDefaultQuantityForDispatch(): ?int
    {
        return $this->defaultQuantityForDispatch;
    }

    public function setDefaultQuantityForDispatch(?int $defaultQuantityForDispatch): self
    {
        $this->defaultQuantityForDispatch = $defaultQuantityForDispatch;

        return $this;
    }

    /**
     * @return Collection<int, TagTemplate>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

}
