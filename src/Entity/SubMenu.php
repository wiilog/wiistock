<?php

namespace App\Entity;

use App\Repository\SubMenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SubMenuRepository::class)
 */
class SubMenu {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Menu::class, inversedBy="subMenus")
     */
    private ?Menu $menu = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $label = null;

    /**
     * @ORM\OneToMany(targetEntity=Action::class, mappedBy="subMenu")
     */
    private Collection $actions;

    public function __construct() {
        $this->actions = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function getMenu(): ?Menu {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): self {
        if($this->menu && $this->menu !== $menu) {
            $this->menu->removeSubMenu($this);
        }
        $this->menu = $menu;
        if($menu) {
            $menu->addSubMenu($this);
        }

        return $this;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

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
            $action->setSubMenu($this);
        }

        return $this;
    }

    public function removeAction(Action $action): self {
        if($this->actions->removeElement($action)) {
            if($action->getSubMenu() === $this) {
                $action->setSubMenu(null);
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
