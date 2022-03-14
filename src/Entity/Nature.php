<?php

namespace App\Entity;

use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportCollectRequestNature;
use App\Entity\Transport\TransportDeliveryRequestNature;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\NatureRepository')]
class Nature {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

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
    private ?bool $defaultForDispatch = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $displayedOnForms = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $allowedForms = [];

    #[ORM\OneToMany(mappedBy: 'nature', targetEntity: TransportDeliveryRequestNature::class)]
    private Collection $transportDeliveryRequestNatures;

    #[ORM\OneToMany(mappedBy: 'nature', targetEntity: TransportCollectRequestNature::class)]
    private Collection $transportCollectRequestNatures;

    #[ORM\ManyToMany(targetEntity: TemperatureRange::class, inversedBy: 'natures')]
    #[ORM\JoinTable(name: 'location_temperature_range')]
    private Collection $temperatureRanges;

    public function __construct() {
        $this->packs = new ArrayCollection();
        $this->emplacements = new ArrayCollection();
        $this->transportDeliveryRequestNatures = new ArrayCollection();
        $this->transportCollectRequestNatures = new ArrayCollection();
        $this->temperatureRanges = new ArrayCollection();
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

    public function getDisplayed(): ?bool {
        return $this->displayed;
    }

    public function setDisplayed(?bool $displayed): self {
        $this->displayed = $displayed;

        return $this;
    }

    public function getDefaultForDispatch(): ?bool {
        return $this->defaultForDispatch;
    }

    public function setDefaultForDispatch(?bool $defaultForDispatch): self {
        $this->defaultForDispatch = $defaultForDispatch;

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
     * @return Collection<int, TransportDeliveryRequestNature>
     */
    public function getTransportDeliveryRequestNatures(): Collection
    {
        return $this->transportDeliveryRequestNatures;
    }

    public function addTransportDeliveryRequestNature(TransportDeliveryRequestNature $transportDeliveryRequestNature): self
    {
        if (!$this->transportDeliveryRequestNatures->contains($transportDeliveryRequestNature)) {
            $this->transportDeliveryRequestNatures[] = $transportDeliveryRequestNature;
            $transportDeliveryRequestNature->setNature($this);
        }

        return $this;
    }

    public function removeTransportDeliveryRequestNature(TransportDeliveryRequestNature $transportDeliveryRequestNature): self
    {
        if ($this->transportDeliveryRequestNatures->removeElement($transportDeliveryRequestNature)) {
            // set the owning side to null (unless already changed)
            if ($transportDeliveryRequestNature->getNature() === $this) {
                $transportDeliveryRequestNature->setNature(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportCollectRequestNature>
     */
    public function getTransportCollectRequestNatures(): Collection
    {
        return $this->transportCollectRequestNatures;
    }

    public function addTransportCollectRequestNature(TransportCollectRequestNature $transportCollectRequestNature): self
    {
        if (!$this->transportCollectRequestNatures->contains($transportCollectRequestNature)) {
            $this->transportCollectRequestNatures[] = $transportCollectRequestNature;
            $transportCollectRequestNature->setNature($this);
        }

        return $this;
    }

    public function removeTransportCollectRequestNature(TransportCollectRequestNature $transportCollectRequestNature): self
    {
        if ($this->transportCollectRequestNatures->removeElement($transportCollectRequestNature)) {
            // set the owning side to null (unless already changed)
            if ($transportCollectRequestNature->getNature() === $this) {
                $transportCollectRequestNature->setNature(null);
            }
        }

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

}
