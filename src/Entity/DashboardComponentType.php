<?php

namespace App\Entity;

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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $name;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $formConfig = [];

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $exampleKey;

    /**
     * @ORM\OneToMany(targetEntity=DashboardComponent::class, mappedBy="type")
     */
    private $dashboardComponents;

    public function __construct()
    {
        $this->dashboardComponents = new ArrayCollection();
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

    public function getFormConfig(): ?array
    {
        return $this->formConfig;
    }

    public function setFormConfig(array $formConfig): self
    {
        $this->formConfig = $formConfig;

        return $this;
    }

    public function getExampleKey(): ?string
    {
        return $this->exampleKey;
    }

    public function setExampleKey(?string $exampleKey): self
    {
        $this->exampleKey = $exampleKey;

        return $this;
    }

    /**
     * @return Collection|DashboardComponent[]
     */
    public function getDashboardComponents(): Collection
    {
        return $this->dashboardComponents;
    }

    public function addDashboardComponent(DashboardComponent $dashboardComponent): self
    {
        if (!$this->dashboardComponents->contains($dashboardComponent)) {
            $this->dashboardComponents[] = $dashboardComponent;
            $dashboardComponent->setType($this);
        }

        return $this;
    }

    public function removeDashboardComponent(DashboardComponent $dashboardComponent): self
    {
        if ($this->dashboardComponents->removeElement($dashboardComponent)) {
            // set the owning side to null (unless already changed)
            if ($dashboardComponent->getType() === $this) {
                $dashboardComponent->setType(null);
            }
        }

        return $this;
    }
}
