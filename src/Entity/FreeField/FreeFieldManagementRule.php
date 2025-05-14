<?php

namespace App\Entity\FreeField;

use App\Entity\Type\Type;
use App\Repository\FreeField\FreeFielsManagementRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FreeFielsManagementRuleRepository::class)]
class FreeFieldManagementRule {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $requiredCreate = null;

    #[ORM\Column]
    private ?bool $requiredEdit = null;

    #[ORM\Column]
    private ?bool $displayedCreate = null;

    #[ORM\Column]
    private ?bool $displayedEdit = null;

    #[ORM\ManyToOne(inversedBy: 'freeFieldManagementRules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FreeField $freeField = null;

    #[ORM\ManyToOne(inversedBy: 'freeFieldManagementRules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isRequiredCreate(): ?bool
    {
        return $this->requiredCreate;
    }

    public function setRequiredCreate(bool $requiredCreate): static
    {
        $this->requiredCreate = $requiredCreate;

        return $this;
    }

    public function isRequiredEdit(): ?bool
    {
        return $this->requiredEdit;
    }

    public function setRequiredEdit(bool $requiredEdit): static
    {
        $this->requiredEdit = $requiredEdit;

        return $this;
    }

    public function isDisplayedCreate(): ?bool
    {
        return $this->displayedCreate;
    }

    public function setDisplayedCreate(bool $displayedCreate): static
    {
        $this->displayedCreate = $displayedCreate;

        return $this;
    }

    public function isDisplayedEdit(): ?bool
    {
        return $this->displayedEdit;
    }

    public function setDisplayedEdit(bool $displayedEdit): static
    {
        $this->displayedEdit = $displayedEdit;

        return $this;
    }

    public function getFreeField(): ?FreeField
    {
        return $this->freeField;
    }

    public function setFreeField(?FreeField $freeField): static
    {
        $this->freeField = $freeField;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function serialize(): array {
        $freeField = $this->getFreeField();
        $type = $this->getType();
        $categoryType = $type?->getCategory();
        return [
            'id' => $this->getId(),
            'freeFieldId' => $freeField->getId(),
            'label' => $freeField->getLabel(),
            'elements' => $freeField->getElements(),
            'typing' => $freeField->getTypage(),
            'defaultValue' => $freeField->getDefaultValue(),
            'requiredCreate' => $this->isRequiredCreate(),
            'requiredEdit' => $this->isRequiredEdit(),
            'typeId' => $this->getType()?->getId(),
            'categoryType' => $categoryType?->getLabel(),
        ];
    }
}
