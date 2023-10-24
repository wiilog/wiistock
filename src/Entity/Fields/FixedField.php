<?php

namespace App\Entity\Fields;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass()]
abstract class FixedField
{
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
