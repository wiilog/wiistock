<?php

namespace App\Entity;

use App\Repository\DashboardPageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardPageRepository::class)
 */
class DashboardPage
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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $slug;

    /**
     * @ORM\OneToMany(targetEntity=DashboardPageRow::class, mappedBy="page")
     */
    private $dashboardPageRows;

    public function __construct()
    {
        $this->dashboardPageRows = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return Collection|DashboardPageRow[]
     */
    public function getDashboardPageRows(): Collection
    {
        return $this->dashboardPageRows;
    }

    public function addDashboardPageRow(DashboardPageRow $dashboardPageRow): self
    {
        if (!$this->dashboardPageRows->contains($dashboardPageRow)) {
            $this->dashboardPageRows[] = $dashboardPageRow;
            $dashboardPageRow->setPage($this);
        }

        return $this;
    }

    public function removeDashboardPageRow(DashboardPageRow $dashboardPageRow): self
    {
        if ($this->dashboardPageRows->removeElement($dashboardPageRow)) {
            // set the owning side to null (unless already changed)
            if ($dashboardPageRow->getPage() === $this) {
                $dashboardPageRow->setPage(null);
            }
        }

        return $this;
    }
}
