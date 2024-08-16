<?php

namespace App\Entity\Dashboard;

use App\Entity\Action;
use App\Repository\Dashboard as DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DashboardRepository\PageRepository::class)]
#[ORM\Table(name: 'dashboard_page')]
class Page {

    public const DASHBOARD_FEED_TOPIC = "dashboard-feed";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(targetEntity: PageRow::class, mappedBy: 'page')]
    private Collection $rows;

    #[ORM\OneToOne(targetEntity: Action::class, cascade: ['persist', 'remove'], inversedBy: 'dashboard')]
    private ?Action $action = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $componentsCount = null;

    public function __construct() {
        $this->rows = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(?string $name): self {
        $this->name = $name;
        $this->getAction()
            ->setLabel("Dashboard \"$name\"");

        return $this;
    }

    /**
     * @return Collection|PageRow[]
     */
    public function getRows(): Collection {
        return $this->rows;
    }

    public function addRow(PageRow $pageRow): self {
        if(!$this->rows->contains($pageRow)) {
            $this->rows[] = $pageRow;
            $pageRow->setPage($this);
        }

        return $this;
    }

    public function removeRow(PageRow $pageRow): self {
        if($this->rows->removeElement($pageRow)) {
            // set the owning side to null (unless already changed)
            if($pageRow->getPage() === $this) {
                $pageRow->setPage(null);
            }
        }

        return $this;
    }

    public function getAction(): ?Action {
        return $this->action;
    }

    public function setAction(?Action $action): self {
        $this->action?->setDashboard($this);

        $this->action = $action;
        if($action->getDashboard() !== $this) {
            $action->setDashboard($this);
        }

        return $this;
    }

    public function getComponentsCount(): ?int {
        return $this->componentsCount;
    }

    public function setComponentsCount(?int $componentsCount): self {
        $this->componentsCount = $componentsCount;
        return $this;
    }
}
