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
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\OneToMany(targetEntity=DashboardPageRow::class, mappedBy="page")
     */
    private $rows;

    public function __construct()
    {
        $this->rows = new ArrayCollection();
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

    /**
     * @return Collection|DashboardPageRow[]
     */
    public function getRows(): Collection
    {
        return $this->rows;
    }

    public function addRow(DashboardPageRow $dashboardPageRow): self
    {
        if (!$this->rows->contains($dashboardPageRow)) {
            $this->rows[] = $dashboardPageRow;
            $dashboardPageRow->setPage($this);
        }

        return $this;
    }

    public function removeRow(DashboardPageRow $dashboardPageRow): self
    {
        if ($this->rows->removeElement($dashboardPageRow)) {
            // set the owning side to null (unless already changed)
            if ($dashboardPageRow->getPage() === $this) {
                $dashboardPageRow->setPage(null);
            }
        }

        return $this;
    }
}
