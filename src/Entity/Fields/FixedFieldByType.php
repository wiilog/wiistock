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
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Type>
     */
    public function getRequiredCreate(): Collection
    {
        return $this->requiredCreate;
    }

    public function addRequiredCreate(Type $requiredCreate): static
    {
        if (!$this->requiredCreate->contains($requiredCreate)) {
            $this->requiredCreate->add($requiredCreate);
        }

        return $this;
    }

    public function removeRequiredCreate(Type $requiredCreate): static
    {
        $this->requiredCreate->removeElement($requiredCreate);

        return $this;
    }

    /**
     * @return Collection<int, Type>
     */
    public function getRequiredEdit(): Collection
    {
        return $this->requiredEdit;
    }

    public function addRequiredEdit(Type $requiredEdit): static
    {
        if (!$this->requiredEdit->contains($requiredEdit)) {
            $this->requiredEdit->add($requiredEdit);
        }

        return $this;
    }

    public function removeRequiredEdit(Type $requiredEdit): static
    {
        $this->requiredEdit->removeElement($requiredEdit);

        return $this;
    }

    /**
     * @return Collection<int, Type>
     */
    public function getKeptInMemory(): Collection
    {
        return $this->keptInMemory;
    }

    public function addKeptInMemory(Type $keptInMemory): static
    {
        if (!$this->keptInMemory->contains($keptInMemory)) {
            $this->keptInMemory->add($keptInMemory);
        }

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

    public function removeDisplayedCreate(Type $displayedCreate): static
    {
        $this->displayedCreate->removeElement($displayedCreate);

        return $this;
    }

    /**
     * @return Collection<int, Type>
     */
    public function getDisplayedEdit(): Collection
    {
        return $this->displayedEdit;
    }

    public function addDisplayedEdit(Type $displayedEdit): static
    {
        if (!$this->displayedEdit->contains($displayedEdit)) {
            $this->displayedEdit->add($displayedEdit);
        }

        return $this;
    }

    public function removeDisplayedEdit(Type $displayedEdit): static
    {
        $this->displayedEdit->removeElement($displayedEdit);

        return $this;
    }

    /**
     * @return Collection<int, Type>
     */
    public function getDisplayedFilters(): Collection
    {
        return $this->displayedFilters;
    }

    public function addDisplayedFilter(Type $displayedFilter): static
    {
        if (!$this->displayedFilters->contains($displayedFilter)) {
            $this->displayedFilters->add($displayedFilter);
        }

        return $this;
    }

    public function removeDisplayedFilter(Type $displayedFilter): static
    {
        $this->displayedFilters->removeElement($displayedFilter);

        return $this;
    }

    /**
     * @return Collection<int, Type>
     */
    public function getOnMobile(): Collection
    {
        return $this->onMobile;
    }

    public function addOnMobile(Type $onMobile): static
    {
        if (!$this->onMobile->contains($onMobile)) {
            $this->onMobile->add($onMobile);
        }

        return $this;
    }

    public function removeOnMobile(Type $onMobile): static
    {
        $this->onMobile->removeElement($onMobile);

        return $this;
    }

    /**
     * @return Collection<int, Type>
     */
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

    public function removeOnLabel(Type $onLabel): static
    {
        $this->onLabel->removeElement($onLabel);

        return $this;
    }
}
