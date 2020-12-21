<?php

namespace App\Entity\Dashboard;

use App\Repository\Dashboard as DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\PageRowRepository::class)
 */
class PageRow
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
     * @ORM\ManyToOne(targetEntity=Page::class, inversedBy="rows")
     */
    private $page;

    /**
     * @ORM\OneToMany(targetEntity=Component::class, mappedBy="row")
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

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): self
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return Collection|Component[]
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function addComponent(Component $component): self
    {
        if (!$this->components->contains($component)) {
            $this->components[] = $component;
            $component->setRow($this);
        }

        return $this;
    }

    public function removeComponent(Component $component): self
    {
        if ($this->components->removeElement($component)) {
            // set the owning side to null (unless already changed)
            if ($component->getRow() === $this) {
                $component->setRow(null);
            }
        }

        return $this;
    }
}
