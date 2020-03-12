<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MenuRepository")
 */
class Menu
{
	const ACCUEIL = 'accueil';
	const TRACA = 'tracabilité';
	const QUALI = 'qualité';
	const DEM = 'demande';
	const ORDRE = 'ordre';
	const STOCK = 'stock';
	const REFERENTIEL = 'référentiel';
	const PARAM = 'paramétrage';
	const NOMADE = 'nomade';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity="Action", mappedBy="menu")
     */
    private $actions;

    public function __construct()
    {
        $this->actions = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|action[]
     */
    public function getActions(): Collection
    {
        return $this->actions;
    }

    public function addAction(action $action): self
    {
        if (!$this->actions->contains($action)) {
            $this->actions[] = $action;
            $action->setMenu($this);
        }

        return $this;
    }

    public function removeAction(action $action): self
    {
        if ($this->actions->contains($action)) {
            $this->actions->removeElement($action);
            // set the owning side to null (unless already changed)
            if ($action->getMenu() === $this) {
                $action->setMenu(null);
            }
        }

        return $this;
    }

}
