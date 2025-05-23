<?php

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu {

    const GENERAL = 'général';
    const DASHBOARDS = 'dashboard';
    const TRACA = 'traçabilité';
    const QUALI = 'qualité & urgences';
    const DEM = 'demande';
    const ORDRE = 'ordre';
    const STOCK = 'stock';
    const PRODUCTION = 'production';
    const REFERENTIEL = 'référentiel';
    const IOT = 'iot';
    const PARAM = 'paramétrage';
    const NOMADE = 'nomade';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $translation = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sorting = null;

    #[ORM\OneToMany(targetEntity: SubMenu::class, mappedBy: 'menu')]
    private Collection $subMenus;

    #[ORM\OneToMany(targetEntity: 'Action', mappedBy: 'menu')]
    private Collection $actions;

    public function __construct() {
        $this->subMenus = new ArrayCollection();
        $this->actions = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getTranslation(): ?string {
        return $this->translation;
    }

    public function setTranslation(?string $translation): Menu {
        $this->translation = $translation;
        return $this;
    }

    public function getSorting(): ?int {
        return $this->sorting;
    }

    public function setSorting(?int $sorting): self {
        $this->sorting = $sorting;
        return $this;
    }

    /**
     * @return Collection|SubMenu[]
     */
    public function getSubMenus(): Collection {
        return $this->subMenus;
    }

    public function addSubMenu(SubMenu $subMenu): self {
        if(!$this->subMenus->contains($subMenu)) {
            $this->subMenus[] = $subMenu;
            $subMenu->setMenu($this);
        }

        return $this;
    }

    public function removeSubMenu(SubMenu $subMenu): self {
        if($this->subMenus->removeElement($subMenu)) {
            if($subMenu->getMenu() === $this) {
                $subMenu->setMenu(null);
            }
        }

        return $this;
    }

    public function setSubMenus(?array $subMenus): self {
        foreach($this->getSubMenus()->toArray() as $subMenu) {
            $this->removeSubMenu($subMenu);
        }

        $this->subMenus = new ArrayCollection();
        foreach($subMenus as $subMenu) {
            $this->addSubMenu($subMenu);
        }

        return $this;
    }

    /**
     * @return Collection|Action[]
     */
    public function getActions(): Collection {
        return $this->actions;
    }

    public function addAction(Action $action): self {
        if(!$this->actions->contains($action)) {
            $this->actions[] = $action;
            $action->setMenu($this);
        }

        return $this;
    }

    public function removeAction(Action $action): self {
        if($this->actions->removeElement($action)) {
            if($action->getMenu() === $this) {
                $action->setMenu(null);
            }
        }

        return $this;
    }

    public function setActions(?array $actions): self {
        foreach($this->getActions()->toArray() as $action) {
            $this->removeAction($action);
        }

        $this->actions = new ArrayCollection();
        foreach($actions as $action) {
            $this->addAction($action);
        }

        return $this;
    }

}
