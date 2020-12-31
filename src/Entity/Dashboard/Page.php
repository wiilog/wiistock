<?php

namespace App\Entity\Dashboard;

use App\Entity\Action;
use App\Repository\Dashboard as DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\PageRepository::class)
 * @ORM\Table(name="dashboard_page")
 */
class Page
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
     * @ORM\OneToMany(targetEntity=PageRow::class, mappedBy="page")
     */
    private $rows;

    /**
     * @ORM\OneToOne(targetEntity=Action::class, cascade={"persist", "remove"}, inversedBy="dashboard")
     */
    private $action;

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
        $this->getAction()
            ->setLabel("Dashboard \"$name\"");

        return $this;
    }

    /**
     * @return Collection|PageRow[]
     */
    public function getRows(): Collection
    {
        return $this->rows;
    }

    public function addRow(PageRow $pageRow): self
    {
        if (!$this->rows->contains($pageRow)) {
            $this->rows[] = $pageRow;
            $pageRow->setPage($this);
        }

        return $this;
    }

    public function removeRow(PageRow $pageRow): self
    {
        if ($this->rows->removeElement($pageRow)) {
            // set the owning side to null (unless already changed)
            if ($pageRow->getPage() === $this) {
                $pageRow->setPage(null);
            }
        }

        return $this;
    }

    public function getAction(): ?Action {
        return $this->action;
    }

    public function setAction(?Action $action): self {
        if($this->action != null) {
            $this->action->setDashboard($this);
        }

        $this->action = $action;
        if($action->getDashboard() !== $this) {
            $action->setDashboard($this);
        }

        return $this;
    }
}
