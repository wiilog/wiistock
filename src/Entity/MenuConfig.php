<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MenuConfigRepository")
 */
class MenuConfig
{
	const SUBMENUS = [
		'tracabilité' => ['arrivages', 'mouvements', 'acheminements', 'associations BR', 'encours', 'urgences'],
		'qualité' => ['litiges'],
		'demande' => ['collectes', 'livraisons', 'manutentions'],
		'ordre' => ['collectes', 'livraisons', 'préparations', 'réceptions'],
		'stock' => ['articles', 'références', 'articles fournisseurs', 'mouvements de stock', 'inventaires', 'alertes'],
		'référentiel' => ['fournisseurs', 'emplacements', 'chauffeurs', 'transporteurs'],
	];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private $menu;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private $submenu;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $display;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenu(): ?string
    {
        return $this->menu;
    }

    public function setMenu(?string $menu): self
    {
        $this->menu = $menu;

        return $this;
    }

    public function getSubmenu(): ?string
    {
        return $this->submenu;
    }

    public function setSubmenu(?string $submenu): self
    {
        $this->submenu = $submenu;

        return $this;
    }

    public function getDisplay(): ?bool
    {
        return $this->display;
    }

    public function setDisplay(?bool $display): self
    {
        $this->display = $display;

        return $this;
    }
}
