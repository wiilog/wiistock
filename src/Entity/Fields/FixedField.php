<?php

namespace App\Entity\Fields;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass()]
abstract class FixedField
{
    #[ORM\Column(length: 255)]
    private ?string $entityCode = null;

    #[ORM\Column(length: 255)]
    private ?string $fieldCode = null;

    #[ORM\Column(length: 255)]
    private ?string $fieldLabel = null;

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
}
