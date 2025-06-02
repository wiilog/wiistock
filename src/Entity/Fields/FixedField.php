<?php

namespace App\Entity\Fields;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass()]
abstract class FixedField {
    public const ENTITY_CODES_MANAGE_BY_TYPE = [
        FixedFieldStandard::ENTITY_CODE_DISPATCH,
        FixedFieldStandard::ENTITY_CODE_PRODUCTION,
    ];

    public const MEMORY_UNKEEPABLE_FIELDS = [
        FixedFieldStandard::ENTITY_CODE_ARRIVAGE => [
            FixedFieldStandard::FIELD_CODE_ARRIVAL_TYPE,
            FixedFieldStandard::FIELD_CODE_PJ_ARRIVAGE,
        ],
    ];

    public const FILTER_ONLY_FIELDS = [
        FixedFieldStandard::ENTITY_CODE_ARRIVAGE => [
            FixedFieldStandard::FIELD_CODE_ARRIVAL_TYPE,
            FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_CARRIER,
        ],
        FixedFieldStandard::ENTITY_CODE_DEMANDE => [
            FixedFieldStandard::FIELD_CODE_TYPE_DEMANDE,
            FixedFieldStandard::FIELD_CODE_DESTINATION_DEMANDE,
        ],
        FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL => [
            FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_CARRIER,
        ],
    ];

    public const FILTERED_FIELDS = [
        FixedFieldStandard::ENTITY_CODE_ARRIVAGE => [
            FixedFieldStandard::FIELD_CODE_CUSTOMS_ARRIVAGE,
            FixedFieldStandard::FIELD_CODE_FROZEN_ARRIVAGE,
            FixedFieldStandard::FIELD_CODE_FOURNISSEUR,
            FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE,
            FixedFieldStandard::FIELD_CODE_TRANSPORTEUR,
            FixedFieldStandard::FIELD_CODE_RECEIVERS,
            FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT,
            FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER,
            FixedFieldStandard::FIELD_CODE_ARRIVAL_TYPE,
            FixedFieldStandard::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE,
            FixedFieldStandard::FIELD_CODE_NUM_COMMANDE_ARRIVAGE,
        ],
        FixedFieldStandard::ENTITY_CODE_HANDLING => [
            FixedFieldStandard::FIELD_CODE_EMERGENCY,
            FixedFieldStandard::FIELD_CODE_RECEIVERS_HANDLING,
            FixedFieldEnum::object->name
        ],
        FixedFieldStandard::ENTITY_CODE_DISPATCH => [
            FixedFieldStandard::FIELD_CODE_EMERGENCY,
            FixedFieldStandard::FIELD_CODE_RECEIVER_DISPATCH,
            FixedFieldStandard::FIELD_CODE_COMMAND_NUMBER_DISPATCH,
            FixedFieldStandard::FIELD_CODE_DESTINATION,
            FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT,
            FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER,
            FixedFieldStandard::FIELD_CODE_LOCATION_PICK,
            FixedFieldStandard::FIELD_CODE_LOCATION_DROP,
            FixedFieldStandard::FIELD_CODE_REQUESTER_DISPATCH,
            FixedFieldStandard::FIELD_CODE_CARRIER_DISPATCH,
        ],

        FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL => [
            FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_CARRIER,
            FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_DRIVER,
            FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_REGISTRATION_NUMBER,
            FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_UNLOADING_LOCATION,
            FixedFieldEnum::carrierTrackingNumber->name
        ],
        FixedFieldStandard::ENTITY_CODE_DEMANDE => [
            FixedFieldStandard::FIELD_CODE_DELIVERY_REQUEST_PROJECT
        ],
        FixedFieldStandard::ENTITY_CODE_RECEPTION => [
            FixedFieldStandard::FIELD_CODE_FOURNISSEUR,
            FixedFieldStandard::FIELD_CODE_TRANSPORTEUR,
        ],
        FixedFieldStandard::ENTITY_CODE_PRODUCTION => [
            FixedFieldEnum::manufacturingOrderNumber->name,
            FixedFieldEnum::emergency->name,
            FixedFieldEnum::expectedAt->name,
            FixedFieldEnum::productArticleCode->name,
            FixedFieldEnum::dropLocation->name,
            FixedFieldEnum::attachments->name,
        ],
    ];

    public const ALWAYS_REQUIRED_FIELDS = [
        FixedFieldStandard::ENTITY_CODE_DISPATCH => [
            FixedFieldStandard::FIELD_CODE_REQUESTER_DISPATCH,
            FixedFieldStandard::FIELD_CODE_LOCATION_PICK,
            FixedFieldStandard::FIELD_CODE_LOCATION_DROP,
            FixedFieldStandard::FIELD_CODE_TYPE_DISPATCH,
        ],
        FixedFieldStandard::ENTITY_CODE_DEMANDE => [
            FixedFieldStandard::FIELD_CODE_TYPE_DEMANDE,
            FixedFieldStandard::FIELD_CODE_DESTINATION_DEMANDE,
        ],
        FixedFieldStandard::ENTITY_CODE_RECEPTION => [
            FixedFieldStandard::FIELD_CODE_EMPLACEMENT,
        ],
        FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL => [
            FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_CARRIER,
        ],
        FixedFieldStandard::ENTITY_CODE_PRODUCTION => [
            FixedFieldEnum::attachments->name,
        ],
        FixedFieldStandard::ENTITY_CODE_HANDLING => [
            FixedFieldEnum::status->name,
            FixedFieldEnum::type->name,
        ],
    ];

    public const ALWAYS_DISPLAYED_FIELDS = [
        FixedFieldStandard::ENTITY_CODE_DISPATCH => [
            FixedFieldStandard::FIELD_CODE_TYPE_DISPATCH,
        ],
        FixedFieldStandard::ENTITY_CODE_RECEPTION => [
            FixedFieldStandard::FIELD_CODE_EMPLACEMENT,
        ],
        FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL => [
            FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_CARRIER,
        ],
        FixedFieldStandard::ENTITY_CODE_HANDLING => [
            FixedFieldEnum::status->name,
            FixedFieldEnum::type->name,
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $entityCode = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $fieldCode = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $fieldLabel = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $elements = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $elementsType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityCode(): ?string
    {
        return $this->entityCode;
    }

    public function setEntityCode(string $entityCode): static
    {
        $this->entityCode = $entityCode;

        return $this;
    }

    public function getFieldCode(): ?string
    {
        return $this->fieldCode;
    }

    public function setFieldCode(string $fieldCode): static
    {
        $this->fieldCode = $fieldCode;

        return $this;
    }

    public function getFieldLabel(): ?string
    {
        return $this->fieldLabel;
    }

    public function setFieldLabel(string $fieldLabel): static
    {
        $this->fieldLabel = $fieldLabel;

        return $this;
    }

    public function getElements(): ?array
    {
        return $this->elements;
    }

    public function setElements(?array $elements): static
    {
        $this->elements = $elements;

        return $this;
    }

    public function getElementsType(): ?string
    {
        return $this->elementsType;
    }

    public function setElementsType(?string $elementsType): static
    {
        $this->elementsType = $elementsType;

        return $this;
    }
}
