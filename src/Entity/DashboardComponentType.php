<?php

namespace App\Entity;

use App\Entity\Interfaces\FormConfig;
use App\Repository\DashboardComponentTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardComponentTypeRepository::class)
 */
class DashboardComponentType
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="json")
     */
    private $formConfig = [];

    /**
     * @ORM\Column(type="json")
     */
    private $exampleValues;

    /**
     * @ORM\OneToMany(targetEntity=DashboardComponent::class, mappedBy="type")
     */
    private $componentsUsing;

    public function __construct()
    {
        $this->componentsUsing = new ArrayCollection();
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

    /**
     * @return FormConfig[]
     */
    public function getFormConfig(): ?array
    {
        return $this->formConfig;
    }

    public function setFormConfig(array $formConfig): self
    {
        $this->formConfig = $formConfig;

        return $this;
    }

    public function getExampleValues(): ?string
    {
        return $this->exampleValues;
    }

    public function setExampleValues(?string $exampleValues): self
    {
        $this->exampleValues = $exampleValues;

        return $this;
    }

    /**
     * @return Collection|DashboardComponent[]
     */
    public function getComponentsUsing(): Collection
    {
        return $this->componentsUsing;
    }

    public function addDashboardComponent(DashboardComponent $dashboardComponent): self
    {
        if (!$this->componentsUsing->contains($dashboardComponent)) {
            $this->componentsUsing[] = $dashboardComponent;
            $dashboardComponent->setType($this);
        }

        return $this;
    }

    public function removeDashboardComponent(DashboardComponent $dashboardComponent): self
    {
        if ($this->componentsUsing->removeElement($dashboardComponent)) {
            // set the owning side to null (unless already changed)
            if ($dashboardComponent->getType() === $this) {
                $dashboardComponent->setType(null);
            }
        }

        return $this;
    }
}
