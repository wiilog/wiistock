<?php

namespace App\Entity;

use App\Entity\Fields\FixedFieldStandard;
use App\Helper\FormatHelper;
use App\Repository\DispatchLabelConfigurationFieldRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;

#[ORM\Entity(repositoryClass: DispatchLabelConfigurationFieldRepository::class)]
class DispatchLabelConfigurationField {

    public const FIELD_TYPE = "otherfield-type";
    public const FIELD_LABELS = [
        "type" => "Type",
        "status" => "Statut",
        "number" => "N° demande",
        "requester" => "Demandeur",
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DispatchLabelConfiguration::class, inversedBy: "fields")]
    #[ORM\JoinColumn(nullable: false)]
    private ?DispatchLabelConfiguration $dispatchLabelConfiguration = null;

    #[ORM\ManyToOne(targetEntity: FixedFieldStandard::class)] // TODO Vérifier avec les changements sur les champs fixes
    #[ORM\JoinColumn(nullable: false)]
    private ?FixedFieldStandard $fieldParam = null;

    #[ORM\ManyToOne(targetEntity: FreeField::class)]
    private ?FreeField $freeField = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $otherField = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $position = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDispatchLabelConfiguration(): ?DispatchLabelConfiguration {
        return $this->dispatchLabelConfiguration;
    }

    public function setDispatchLabelConfiguration(?DispatchLabelConfiguration $dispatchLabelConfiguration): self {
        if($this->dispatchLabelConfiguration && $this->dispatchLabelConfiguration !== $dispatchLabelConfiguration) {
            $this->dispatchLabelConfiguration->removeField($this);
        }
        $this->dispatchLabelConfiguration = $dispatchLabelConfiguration;
        if($dispatchLabelConfiguration) {
            $dispatchLabelConfiguration->addField($this);
        }

        return $this;
    }

    public function getFieldId(): string {
        if($this->getFieldParam()) {
            return "fieldparam-{$this->getFieldParam()->getId()}";
        } else if($this->getFreeField()) {
            return "freefield-{$this->getFreeField()->getId()}";
        } else if($this->getOtherField()) {
            return "otherfield-{$this->getOtherField()}";
        } else {
            throw new RuntimeException("Invalid field");
        }
    }

    public function getLabel(): string {
        if($this->getFieldParam()) {
            return $this->getFieldParam()->getFieldLabel();
        } else if($this->getFreeField()) {
            return $this->getFreeField()->getLabel();
        } else if($this->getOtherField()) {
            return self::FIELD_LABELS[$this->getOtherField()];
        } else {
            throw new RuntimeException("Invalid field");
        }
    }

    public function getFieldParam(): ?FixedFieldStandard {
        return $this->fieldParam;
    }

    public function setFieldParam(?FixedFieldStandard $fieldParam): self {
        $this->fieldParam = $fieldParam;

        return $this;
    }

    public function getFreeField(): ?FreeField {
        return $this->freeField;
    }

    public function setFreeField(?FreeField $freeField): self {
        $this->freeField = $freeField;

        return $this;
    }

    public function getOtherField(): ?string {
        return $this->otherField;
    }

    public function setOtherField(?string $otherField): self {
        $this->otherField = $otherField;
        return $this;
    }

    public function getPosition(): ?int {
        return $this->position;
    }

    public function setPosition(int $position): self {
        $this->position = $position;

        return $this;
    }

    public function getValue(Dispatch $dispatch) {
        if($this->getOtherField()) {
            switch($this->getOtherField()) {
                case "type":
                    return $dispatch->getType()->getLabel();
                case "status":
                    return $dispatch->getStatut()->getNom();
                case "number":
                    return $dispatch->getNumber();
                case "requester":
                    return FormatHelper::user($dispatch->getRequester());
            }
        } else if($this->getFreeField()) {
            return $dispatch->getFreeFieldValue($this->getFreeField());
        } else if($this->getFieldParam()) {
            switch($this->getFieldParam()->getFieldCode()) {
                case FieldsParam::FIELD_CODE_CARRIER_DISPATCH:
                    return $dispatch->getCarrier() ? $dispatch->getCarrier()->getLabel() : "";
                case FieldsParam::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH:
                    return $dispatch->getCarrierTrackingNumber();
                case FieldsParam::FIELD_CODE_RECEIVER_DISPATCH:
                    return FormatHelper::users($dispatch->getReceivers());
                case FieldsParam::FIELD_CODE_EMERGENCY:
                    return $dispatch->getEmergency();
                case FieldsParam::FIELD_CODE_COMMAND_NUMBER_DISPATCH:
                    return $dispatch->getCommandNumber();
                case FieldsParam::FIELD_CODE_COMMENT_DISPATCH:
                    return strip_tags($dispatch->getCommentaire());
                case FieldsParam::FIELD_CODE_BUSINESS_UNIT:
                    return $dispatch->getBusinessUnit();
                case FieldsParam::FIELD_CODE_PROJECT_NUMBER:
                    return $dispatch->getProjectNumber();
                case FieldsParam::FIELD_CODE_LOCATION_PICK:
                    return FormatHelper::location($dispatch->getLocationFrom());
                case FieldsParam::FIELD_CODE_LOCATION_DROP:
                    return FormatHelper::location($dispatch->getLocationTo());
                case FieldsParam::FIELD_CODE_DESTINATION:
                    return $dispatch->getDestination();
                case FieldsParam::FIELD_CODE_DUE_DATE_ONE:
                    return FormatHelper::datetime($dispatch->getDueDate1());
                case FieldsParam::FIELD_CODE_DUE_DATE_TWO:
                    return FormatHelper::datetime($dispatch->getDueDate2());
                case FieldsParam::FIELD_CODE_DUE_DATE_TWO_BIS:
                    return FormatHelper::datetime($dispatch->getDueDate2Bis());
                case FieldsParam::FIELD_CODE_PRODUCTION_ORDER_NUMBER:
                    return $dispatch->getProductionOrderNumber();
                case FieldsParam::FIELD_CODE_PRODUCTION_REQUEST:
                    return $dispatch->getProductionRequest();
            }
        }

        return null;
    }

}
