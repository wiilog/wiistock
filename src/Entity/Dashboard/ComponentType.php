<?php

namespace App\Entity\Dashboard;

use App\Repository\Dashboard as DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: DashboardRepository\ComponentTypeRepository::class)]
#[ORM\Table(name: 'dashboard_component_type')]
class ComponentType
{

    public const CATEGORY_TRACKING = "Traçabilité";
    public const CATEGORY_REQUESTS = "Demandes";
    public const CATEGORY_ORDERS = "Ordres";
    public const CATEGORY_STOCK = "Stock";
    public const CATEGORY_OTHER = "Autre";

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
    public const HANDLING_TRACKING = 'handling_tracking';
    public const DAILY_HANDLING_INDICATOR = 'daily_handling_indicator';
    public const DAILY_HANDLING = 'daily_handling';
    public const DAILY_OPERATIONS = 'daily_operations';
    public const DAILY_DELIVERY_ORDERS = 'daily_delivery_orders';
    public const PENDING_REQUESTS = 'pending_requests';
    public const EXTERNAL_IMAGE = 'external_image';
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
    public const REQUESTS_TO_TREAT_SHIPPING = 'requests_to_treat_shipping';
    public const GENERIC_TEMPLATE = 'generic_template';

    public const REQUESTS_SELF = 'self';
    public const REQUESTS_EVERYONE = 'everyone';

    public const ENTITY_TO_TREAT_REGEX_TREATMENT_DELAY = '/^(([01]?[0-9])|(2[0-3])):[0-5][0-9]$/';
    public const DEFAULT_CHART_COLOR = '#A3D1FF';

    public const COMPONENT_ORDER = [
        self::CATEGORY_TRACKING => [
            self::ONGOING_PACKS,
            self::DAILY_ARRIVALS,
            self::LATE_PACKS,
            self::DAILY_ARRIVALS_AND_PACKS,
            self::RECEIPT_ASSOCIATION,
            self::WEEKLY_ARRIVALS_AND_PACKS,
            self::DROP_OFF_DISTRIBUTED_PACKS,
            self::CARRIER_TRACKING,
            self::ARRIVALS_EMERGENCIES_TO_RECEIVE,
            self::DAILY_ARRIVALS_EMERGENCIES,
        ],
        self::CATEGORY_ORDERS => [
            self::PACK_TO_TREAT_FROM,
            self::ENTRIES_TO_HANDLE,
            self::ORDERS_TO_TREAT,
            self::DAILY_DELIVERY_ORDERS
        ],
        self::CATEGORY_STOCK => [
            self::ACTIVE_REFERENCE_ALERTS,
            self::MONETARY_RELIABILITY_GRAPH,
            self::MONETARY_RELIABILITY_INDICATOR,
            self::REFERENCE_RELIABILITY,
        ],
        self::CATEGORY_REQUESTS => [
            self::REQUESTS_TO_TREAT,
            self::HANDLING_TRACKING,
            self::PENDING_REQUESTS,
            self::DAILY_DISPATCHES,
            self::DAILY_HANDLING,
            self::DAILY_OPERATIONS,
            self::DAILY_HANDLING_INDICATOR,
        ],
        self::CATEGORY_OTHER => [
            self::EXTERNAL_IMAGE,
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $template;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $hint;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $category;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $exampleValues;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $meterKey;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private ?bool $inSplitCell;

    #[ORM\OneToMany(targetEntity: Component::class, mappedBy: 'type', cascade: ['remove'])]
    private Collection $componentsUsing;

    public function __construct()
    {
        $this->componentsUsing = new ArrayCollection();
        $this->exampleValues = [];
        $this->inSplitCell = true;
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

    public function setCategory(?string $category): self
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

    public function isInSplitCell(): bool
    {
        return $this->inSplitCell;
    }

    public function setInSplitCell(bool $inSplitCell): self
    {
        $this->inSplitCell = $inSplitCell;
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

    public function getMeterKey(): ?string
    {
        return $this->meterKey;
    }

    public function setMeterKey(?string $meterKey): self
    {
        $this->meterKey = $meterKey;
        return $this;
    }

    private function decodeData(array $data): array
    {
        return Stream::from($data)
            ->keymap(function ($value) {
                return [$value['dataKey'], $value['data']];
            })->toArray();
    }

    private function encodeData(array $data): array
    {
        $savedData = [];
        foreach ($data as $key => $datum) {
            $savedData[] = [
                'dataKey' => $key,
                'data' => $datum,
            ];
        }
        return $savedData;
    }

}
