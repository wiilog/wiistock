<?php

namespace App\Entity\Dashboard;

use App\Repository\Dashboard as DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\ComponentTypeRepository::class)
 * @ORM\Table(name="dashboard_component_type")
 */
class ComponentType
{
    public const ONGOING_PACKS = 'ongoing_packs';
    public const DAILY_ARRIVALS = 'daily_arrivals';
    public const LATE_PACKS = 'late_packs';
    public const DAILY_ARRIVALS_AND_PACKS = 'daily_arrivals_and_packs';
    public const WEEKLY_ARRIVALS_AND_PACKS = 'weekly_arrivals_and_packs';
    public const CARRIER_INDICATOR = 'carrier_indicator';
    public const RECEIPT_ASSOCIATION = 'receipt_association';
    public const PACK_TO_TREAT_FROM = 'pack_to_treat_from';

    public const INDICATOR_TYPE = 'Indicateurs';
    public const GRAPH_TYPE = 'Graphiques';
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $template;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $hint;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $category;

    /**
     * @ORM\Column(type="json")
     */
    private $exampleValues;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $meterKey;

    /**
     * @ORM\OneToMany(targetEntity=Component::class, mappedBy="type")
     */
    private $componentsUsing;

    public function __construct()
    {
        $this->componentsUsing = new ArrayCollection();
        $this->exampleValues = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function setHint(string $hint): self
    {
        $this->hint = $hint;
        return $this;
    }

    public function getExampleValues(): ?array
    {
        return $this->exampleValues;
    }

    public function setExampleValues(array $exampleValues): self
    {
        $this->exampleValues = $exampleValues;

        return $this;
    }

    /**
     * @return Collection|Component[]
     */
    public function getComponentsUsing(): Collection
    {
        return $this->componentsUsing;
    }

    public function addComponentUsing(Component $component): self
    {
        if (!$this->componentsUsing->contains($component)) {
            $this->componentsUsing[] = $component;
            $component->setType($this);
        }

        return $this;
    }

    public function removeComponentUsing(Component $component): self
    {
        if ($this->componentsUsing->removeElement($component)) {
            // set the owning side to null (unless already changed)
            if ($component->getType() === $this) {
                $component->setType(null);
            }
        }

        return $this;
    }

    public function getMeterKey(): ?string {
        return $this->meterKey;
    }

    public function setMeterKey(?string $meterKey): self {
        $this->meterKey = $meterKey;
        return $this;
    }
}
