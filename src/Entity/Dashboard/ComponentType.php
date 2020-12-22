<?php

namespace App\Entity\Dashboard;

use App\Repository\Dashboard as DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\ComponentTypeRepository::class)
 * @ORM\Table(name="dashboard_component_type")
 */
class ComponentType
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $template;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $hint;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $category;

    /**
     * @ORM\Column(type="json")
     */
    private $exampleValues;

    /**
     * @ORM\OneToMany(targetEntity=Component::class, mappedBy="type")
     */
    private $componentsUsing;

    public function __construct()
    {
        $this->componentsUsing = new ArrayCollection();
        $this->exampleValues = [];
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

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function setHint(string $hint): self
    {
        $this->hint = $hint;
        return $this;
    }

    public function getExampleValues(): ?array
    {
        return $this->exampleValues;
    }

    public function setExampleValues(array $exampleValues): self
    {
        $this->exampleValues = $exampleValues;

        return $this;
    }

    /**
     * @return Collection|Component[]
     */
    public function getComponentsUsing(): Collection
    {
        return $this->componentsUsing;
    }

    public function addComponentUsing(Component $component): self
    {
        if (!$this->componentsUsing->contains($component)) {
            $this->componentsUsing[] = $component;
            $component->setType($this);
        }

        return $this;
    }

    public function removeComponentUsing(Component $component): self
    {
        if ($this->componentsUsing->removeElement($component)) {
            // set the owning side to null (unless already changed)
            if ($component->getType() === $this) {
                $component->setType(null);
            }
        }

        return $this;
    }
}
