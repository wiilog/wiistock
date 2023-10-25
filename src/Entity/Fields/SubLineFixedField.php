<?php

namespace App\Entity\Fields;

use App\Repository\SubLineFieldsParamRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubLineFieldsParamRepository::class)]
class SubLineFixedField extends FixedField {

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
            SubLineFixedField::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT,
            SubLineFixedField::FIELD_CODE_DEMANDE_REF_ARTICLE_NOTES,
        ],
    ];

    public const DISABLED_REQUIRED = [
        self::ENTITY_CODE_DEMANDE_REF_ARTICLE => [
            SubLineFixedField::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT,
        ],
        self::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT => [
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS,
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR,
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE,
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION,
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $displayed = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $displayedUnderCondition = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?string $conditionFixedField = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $conditionFixedFieldValue = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $required = null;

    public function getId(): ?int {
        return $this->id;
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
}
