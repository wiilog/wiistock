<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MenuConfigRepository")
 */
class MenuConfig
{
	const MENU_TRACA = 'tracabilité';
	const MENU_QUALI = 'qualité';
	const MENU_DEM = 'demande';
	const MENU_ORDRE = 'ordre';
	const MENU_STOCK = 'stock';
	const MENU_REF = 'référentiel';

	const SUB_ARRI = 'arrivages';
	const SUB_MOUV = 'mouvements';
	const SUB_ACHE = 'acheminements';
	const SUB_ASSO = 'associations BR';
	const SUB_ENCO = 'encours';
	const SUB_URGE = 'urgences';

	const SUB_LITI = 'litiges';

	const SUB_COLL = 'collectes';
	const SUB_LIVR = 'livraisons';
	const SUB_MANU = 'manutentions';
	const SUB_PREPA = 'préparations';
	const SUB_RECE = 'réceptions';

	const SUB_ARTI = 'articles';
	const SUB_REFE = 'références';
	const SUB_ARTI_FOUR = 'articles fournisseurs';
	const SUB_MOUV_STOC = 'mouvements de stock';
	const SUB_INVE = 'inventaires';
	const SUB_ALER = 'alertes';

	const SUB_FOUR = 'fournisseurs';
	const SUB_EMPL = 'emplacements';
	const SUB_CHAU = 'chauffeurs';
	const SUB_TRAN = 'transporteurs';

	const SUBMENUS = [
		self::MENU_TRACA => [self::SUB_ARRI, self::SUB_MOUV, self::SUB_ACHE, self::SUB_ASSO, self::SUB_ENCO, self::SUB_URGE],
		self::MENU_QUALI => [self::SUB_LITI],
		self::MENU_DEM => [self::SUB_COLL, self::SUB_LIVR, self::SUB_MANU],
		self::MENU_ORDRE => [self::SUB_COLL, self::SUB_LIVR, self::SUB_PREPA, self::SUB_RECE],
		self::MENU_STOCK => [self::SUB_ARTI, self::SUB_REFE, self::SUB_ARTI_FOUR, self::SUB_MOUV_STOC, self::SUB_INVE, self::SUB_ALER],
		self::MENU_REF => [self::SUB_FOUR, self::SUB_EMPL, self::SUB_CHAU, self::SUB_TRAN],
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
