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
    const RECEPTION = 'REC';
    const PREPA = 'PREPA';
    const LIVRAISON = 'LIVR';
    const DEM_LIVRAISON = 'DEMLIVR';
    const DEM_COLLECTE = 'DEMCOL';
    const COLLECTE = 'COL';
    const MANUT = 'MANUT';
    const NOMAD = 'NOMAD';
    const PARAM = 'PARAM';
    const STOCK = 'STOCK';
    const INDICS_ACCUEIL = 'INDICAC';
    const ARRIVAGE = 'ARRIVAGE';

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
     * @ORM\Column(type="string", length=16)
     */
    private $code;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }
}
