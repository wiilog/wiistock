<?php

namespace App\Entity\Fields;

use App\Entity\Type;
use App\Repository\Fields\FixedFieldByTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FixedFieldByTypeRepository::class)]
class FixedFieldByType extends FixedField
{
    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'fixed_field_by_type_required_create')]
    private Collection $requiredCreate;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'fixed_field_by_type_required_edit')]
    private Collection $requiredEdit;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'fixed_field_by_type_kept_in_memory')]
    private Collection $keptInMemory;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'fixed_field_by_type_displayed_create')]
    private Collection $displayedCreate;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'fixed_field_by_type_displayed_edit')]
    private Collection $displayedEdit;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'fixed_field_by_type_displayed_filters')]
    private Collection $displayedFilters;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'fixed_field_by_type_on_mobile')]
    private Collection $onMobile;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'fixed_field_by_type_on_label')]
    private Collection $onLabel;

    public function __construct()
    {
        $this->requiredCreate = new ArrayCollection();
        $this->requiredEdit = new ArrayCollection();
        $this->keptInMemory = new ArrayCollection();
        $this->displayedCreate = new ArrayCollection();
        $this->displayedEdit = new ArrayCollection();
        $this->displayedFilters = new ArrayCollection();
        $this->onMobile = new ArrayCollection();
        $this->onLabel = new ArrayCollection();
    }

    public function getRequiredCreate(): Collection
    {
        return $this->requiredCreate;
    }

    public function isRequiredCreate(Type $type): bool
    {
        return $this->requiredCreate->contains($type);
    }


    public function addRequiredCreate(Type $requiredCreate): static
    {
        if (!$this->requiredCreate->contains($requiredCreate)) {
            $this->requiredCreate->add($requiredCreate);
        }

        return $this;
    }

    public function setRequiredCreate(Collection $requiredCreate): static
    {
        $this->requiredCreate = $requiredCreate;

        return $this;
    }

    public function removeRequiredCreate(Type $requiredCreate): static
    {
        $this->requiredCreate->removeElement($requiredCreate);

        return $this;
    }

    public function getRequiredEdit(): Collection
    {
        return $this->requiredEdit;
    }

    public function isRequiredEdit(Type $type): bool
    {
        return $this->requiredEdit->contains($type);
    }

    public function addRequiredEdit(Type $requiredEdit): static
    {
        if (!$this->requiredEdit->contains($requiredEdit)) {
            $this->requiredEdit->add($requiredEdit);
        }

        return $this;
    }

    public function setRequiredEdit(Collection $requiredEdit): static
    {
        $this->requiredEdit = $requiredEdit;

        return $this;
    }

    public function removeRequiredEdit(Type $requiredEdit): static
    {
        $this->requiredEdit->removeElement($requiredEdit);

        return $this;
    }

    public function getKeptInMemory(): Collection
    {
        return $this->keptInMemory;
    }

    public function isKeptInMemory(Type $type): bool
    {
        return $this->keptInMemory->contains($type);
    }

    public function addKeptInMemory(Type $keptInMemory): static
    {
        if (!$this->keptInMemory->contains($keptInMemory)) {
            $this->keptInMemory->add($keptInMemory);
        }

        return $this;
    }

    public function setKeptInMemory(Collection $keptInMemory): static
    {
        $this->keptInMemory = $keptInMemory;

        return $this;
    }

    public function removeKeptInMemory(Type $keptInMemory): static
    {
        $this->keptInMemory->removeElement($keptInMemory);

        return $this;
    }

    /**
     * @return Collection<int, Type>
     */
    public function getDisplayedCreate(): Collection
    {
        return $this->displayedCreate;
    }

    public function addDisplayedCreate(Type $displayedCreate): static
    {
        if (!$this->displayedCreate->contains($displayedCreate)) {
            $this->displayedCreate->add($displayedCreate);
        }

        return $this;
    }

    public function setDisplayedCreate(Collection $displayedCreate): static
    {
        $this->displayedCreate = $displayedCreate;

        return $this;
    }

    public function removeDisplayedCreate(Type $displayedCreate): static
    {
        $this->displayedCreate->removeElement($displayedCreate);

        return $this;
    }

    public function isDisplayedCreate(Type $type): bool
    {
        return $this->displayedCreate->contains($type);
    }

    public function getDisplayedEdit(): Collection
    {
        return $this->displayedEdit;
    }

    public function isDisplayedEdit(Type $type): bool
    {
        return $this->displayedEdit->contains($type);
    }

    public function addDisplayedEdit(Type $displayedEdit): static
    {
        if (!$this->displayedEdit->contains($displayedEdit)) {
            $this->displayedEdit->add($displayedEdit);
        }

        return $this;
    }

    public function setDisplayedEdit(Collection $displayedEdit): static
    {
        $this->displayedEdit = $displayedEdit;

        return $this;
    }

    public function removeDisplayedEdit(Type $displayedEdit): static
    {
        $this->displayedEdit->removeElement($displayedEdit);

        return $this;
    }

    public function getDisplayedFilters(): Collection
    {
        return $this->displayedFilters;
    }

    public function isDisplayedFilters(Type $type): bool
    {
        return $this->displayedFilters->contains($type);
    }

    public function addDisplayedFilters(Type $displayedFilter): static
    {
        if (!$this->displayedFilters->contains($displayedFilter)) {
            $this->displayedFilters->add($displayedFilter);
        }

        return $this;
    }

    public function setDisplayedFilters(Collection $displayedFilters): static
    {
        $this->displayedFilters = $displayedFilters;

        return $this;
    }

    public function removeDisplayedFilters(Type $displayedFilter): static
    {
        $this->displayedFilters->removeElement($displayedFilter);

        return $this;
    }

    public function getOnMobile(): Collection
    {
        return $this->onMobile;
    }

    public function isOnMobile(Type $type): bool
    {
        return $this->onMobile->contains($type);
    }

    public function addOnMobile(Type $onMobile): static
    {
        if (!$this->onMobile->contains($onMobile)) {
            $this->onMobile->add($onMobile);
        }

        return $this;
    }

    public function setOnMobile(Collection $onMobile): static
    {
        $this->onMobile = $onMobile;

        return $this;
    }

    public function removeOnMobile(Type $onMobile): static
    {
        $this->onMobile->removeElement($onMobile);

        return $this;
    }

    public function getOnLabel(): Collection
    {
        return $this->onLabel;
    }

    public function addOnLabel(Type $onLabel): static
    {
        if (!$this->onLabel->contains($onLabel)) {
            $this->onLabel->add($onLabel);
        }

        return $this;
    }

    public function setOnLabel(Collection $onLabel): static
    {
        $this->onLabel = $onLabel;

        return $this;
    }

    public function isOnLabel(Type $type): bool
    {
        return $this->onLabel->contains($type);
    }

    public function removeOnLabel(Type $onLabel): static
    {
        $this->onLabel->removeElement($onLabel);

        return $this;
    }
}
