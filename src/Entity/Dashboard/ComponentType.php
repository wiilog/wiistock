<?php

namespace App\Entity\Dashboard;

use App\Helper\Stream;
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
    public const CARRIER_TRACKING = 'carrier_tracking';
    public const RECEIPT_ASSOCIATION = 'receipt_association';
    public const PACK_TO_TREAT_FROM = 'pack_to_treat_from';
    public const DROP_OFF_DISTRIBUTED_PACKS = 'drop_off_distributed_packs';
    public const ENTRIES_TO_HANDLE = 'entries_to_handle';
    public const DAILY_ARRIVALS_EMERGENCIES = 'daily_arrivals_emergencies';
    public const ARRIVALS_EMERGENCIES_TO_RECEIVE = 'arrivals_emergencies_to_receive';
    public const MONETARY_RELIABILITY_GRAPH = 'monetary_reliability_graph';
    public const MONETARY_RELIABILITY_INDICATOR = 'monetary_reliability_indicator';
    public const REFERENCE_RELIABILITY = 'reference_reliability';
    public const ACTIVE_REFERENCE_ALERTS = 'active_reference_alerts';
    public const DAILY_DISPATCHES = 'daily_dispatches';
    public const DAILY_HANDLING = 'daily_handling';
    public const PENDING_REQUESTS = 'pending_requests';

    public const ORDERS_TO_TREAT = 'orders_to_treat';
    public const ORDERS_TO_TREAT_COLLECT = 'orders_to_treat_collect';
    public const ORDERS_TO_TREAT_DELIVERY = 'orders_to_treat_delivery';
    public const ORDERS_TO_TREAT_PREPARATION = 'orders_to_treat_preparation';
    public const ORDERS_TO_TREAT_TRANSFER = 'orders_to_treat_transfer';

    public const REQUESTS_TO_TREAT = 'requests_to_treat';
    public const REQUESTS_TO_TREAT_COLLECT = 'requests_to_treat_collect';
    public const REQUESTS_TO_TREAT_HANDLING = 'requests_to_treat_handling';
    public const REQUESTS_TO_TREAT_DELIVERY = 'requests_to_treat_delivery';
    public const REQUESTS_TO_TREAT_DISPATCH = 'requests_to_treat_dispatch';
    public const REQUESTS_TO_TREAT_TRANSFER = 'requests_to_treat_transfer';

    public const ENTITY_TO_TREAT_REGEX_TREATMENT_DELAY = '/^(([01]?[0-9])|(2[0-3])):[0-5][0-9]$/';

    public const CATEGORY_TRACKING = "Traçabilité";
    public const CATEGORY_REQUESTS = "Demandes";
    public const CATEGORY_ORDERS = "Ordres";
    public const CATEGORY_STOCK = "Stock";

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
     * @ORM\OneToMany(targetEntity=Component::class, mappedBy="type", cascade={"remove"})
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

        $exampleValues = $this->exampleValues;
        if (isset($exampleValues['chartData'])) {
            $exampleValues['chartData'] = $this->decodeData($exampleValues['chartData']);
        }

        return $exampleValues;
    }

    public function setExampleValues(array $exampleValues): self
    {
        if (isset($exampleValues['chartData'])) {
            $exampleValues['chartData'] = $this->encodeData($exampleValues['chartData']);
        }

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



    private function decodeData(array $data): array {
        return Stream::from($data)
            ->keymap(function ($value) {
                return [$value['dataKey'], $value['data']];
            })->toArray();
    }

    private function encodeData(array $data): array {
        $savedData = [];
        foreach ($data as $key => $datum) {
            $savedData[] = [
                'dataKey' => $key,
                'data' => $datum
            ];
        }
        return $savedData;
    }
}
