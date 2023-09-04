<?php

namespace App\Entity;

use App\Repository\SubLineFieldsParamRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubLineFieldsParamRepository::class)]
class SubLineFieldsParam {

    public const FREE_ELEMENTS_FIELDS = [
        self::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT => [
            self::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH,
            self::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH,
            self::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT,
        ]
    ];

    const ENTITY_CODE_DEMANDE_REF_ARTICLE = 'demandeRefArticle';
    const ENTITY_CODE_DISPATCH_LOGISTIC_UNIT = 'dispatchLogisticUnit';

    const DISPLAY_CONDITIONS = [
        self::ENTITY_CODE_DEMANDE_REF_ARTICLE => [
            self::DISPLAY_CONDITION_REFERENCE_TYPE,
        ],
    ];

    const DISPLAY_CONDITION_REFERENCE_TYPE = "Type Reference";

    const FIELD_CODE_DEMANDE_REF_ARTICLE_PROJECT = 'project';
    const FIELD_LABEL_DEMANDE_REF_ARTICLE_PROJECT = 'projet';

    const FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT = 'comment';
    const FIELD_LABEL_DEMANDE_REF_ARTICLE_COMMENT = 'commentaire';

    const FIELD_CODE_DEMANDE_REF_ARTICLE_NOTES = 'notes';
    const FIELD_LABEL_DEMANDE_REF_ARTICLE_NOTES = 'remarques';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH = 'length';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LENGTH = 'longueur';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH = 'width';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_WIDTH = 'largeur';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT = 'height';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_HEIGHT = 'hauteur';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT = 'weight';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_WEIGHT = 'poids';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME = 'volume';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_VOLUME = 'volume';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT = 'comment';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_COMMENT = 'commentaire';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE = 'lastTrackingDate';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE = 'date dernier mouvement';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION = 'lastLocation';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION = 'dernier emplacement';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR = 'operator';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_OPERATOR = 'opérateur';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS = 'status';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_STATUS = 'statut';

    const FIELD_CODE_DISPATCH_LOGISTIC_UNIT_NATURE = 'nature';
    const FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_NATURE = 'nature';

    public const DISABLED_DISPLAYED_UNDER_CONDITION = [
        self::ENTITY_CODE_DEMANDE_REF_ARTICLE => [
            SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT,
            SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_NOTES,
        ],
    ];

    public const DISABLED_REQUIRED = [
        self::ENTITY_CODE_DEMANDE_REF_ARTICLE => [
            SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT,
        ],
        self::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT => [
            SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS,
            SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR,
            SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE,
            SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION,
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $entityCode = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $fieldCode = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $fieldLabel = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $displayed = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $displayedUnderCondition = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $conditionFixedField = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $conditionFixedFieldValue = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $required = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $elements = [];

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $elementsType = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getEntityCode(): ?string {
        return $this->entityCode;
    }

    public function setEntityCode(string $entityCode): self {
        $this->entityCode = $entityCode;

        return $this;
    }

    public function getFieldCode(): ?string {
        return $this->fieldCode;
    }

    public function setFieldCode(string $fieldCode): self {
        $this->fieldCode = $fieldCode;

        return $this;
    }

    public function getFieldLabel(): ?string {
        return $this->fieldLabel;
    }

    public function setFieldLabel(string $fieldLabel): self {
        $this->fieldLabel = $fieldLabel;

        return $this;
    }

    public function isDisplayed(): ?bool {
        return $this->displayed;
    }

    public function setDisplayed(?bool $displayed): self {
        $this->displayed = $displayed;

        return $this;
    }

    public function isDisplayedUnderCondition(): ?bool {
        return $this->displayedUnderCondition;
    }

    public function setDisplayedUnderCondition(?bool $displayedUnderCondition): self {
        $this->displayedUnderCondition = $displayedUnderCondition;

        return $this;
    }

    public function getConditionFixedField(): ?string {
        return $this->conditionFixedField;
    }

    public function setConditionFixedField(?string $conditionFixedField): self {
        $this->conditionFixedField = $conditionFixedField;

        return $this;
    }

    public function getConditionFixedFieldValue(): ?array {
        return $this->conditionFixedFieldValue;
    }

    public function setConditionFixedFieldValue(?array $conditionFixedFieldValue): self {
        $this->conditionFixedFieldValue = $conditionFixedFieldValue;

        return $this;
    }

    public function isRequired(): ?bool {
        return $this->required;
    }

    public function setRequired(?bool $required): self {
        $this->required = $required;

        return $this;
    }

    public function getElementsType(): ?string
    {
        return $this->elementsType;
    }

    public function setElementsType(?string $elementsType): self
    {
        $this->elementsType = $elementsType;

        return $this;
    }

    public function getElements(): ?array {
        return $this->elements;
    }

    public function setElements(?array $elements): self {
        $this->elements = $elements;
        return $this;
    }
}
