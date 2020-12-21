<?php

namespace App\Entity;

use App\Repository\DashboardPageRowRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardPageRowRepository::class)
 */
class DashboardPageRow
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $rowSize;

    /**
     * @ORM\ManyToOne(targetEntity=DashboardPage::class, inversedBy="dashboardPageRows")
     */
    private $page;

    /**
     * @ORM\OneToMany(targetEntity=DashboardComponent::class, mappedBy="row")
     */
    private $components;

    public function __construct()
    {
        $this->components = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRowSize(): ?int
    {
        return $this->rowSize;
    }

    public function setRowSize(?int $rowSize): self
    {
        $this->rowSize = $rowSize;

        return $this;
    }

    public function getPage(): ?DashboardPage
    {
        return $this->page;
    }

    public function setPage(?DashboardPage $page): self
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return Collection|DashboardComponent[]
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function addComponent(DashboardComponent $dashboardComponent): self
    {
        if (!$this->components->contains($dashboardComponent)) {
            $this->components[] = $dashboardComponent;
            $dashboardComponent->setRow($this);
        }

        return $this;
    }

    public function removeComponent(DashboardComponent $dashboardComponent): self
    {
        if ($this->components->removeElement($dashboardComponent)) {
            // set the owning side to null (unless already changed)
            if ($dashboardComponent->getRow() === $this) {
                $dashboardComponent->setRow(null);
            }
        }

        return $this;
    }
}
