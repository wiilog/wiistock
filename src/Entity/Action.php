<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ActionRepository")
 */
class Action
{
    const LIST = 'lister';
    const CREATE = 'créer';
    const EDIT = 'modifer';
    const DELETE = 'supprimer';
    const EXPORT = 'exporter';

    // menu accueil
	const DISPLAY_INDI = 'afficher indicateurs';
	const DISPLAY_INDIC_INV_REFERENCE = 'afficher indicateur fiabilité par réference';
	const DISPLAY_INDIC_INV_MONETAIRE = 'afficher indicateur fiabilité monétaire';

	// menu traça
	const DISPLAY_ARRI = 'afficher arrivages';
	const DISPLAY_MOUV = 'afficher mouvements';
	const DISPLAY_ASSO = 'afficher associations BR';
	const DISPLAY_ENCO = 'afficher encours';
	const DISPLAY_PACK = 'afficher colis';
	const DISPLAY_URGE = 'afficher urgences';
	const LIST_ALL = 'lister tous les arrivages';
	const ADD_PACK = 'ajouter colis';
	const EDIT_PACK = 'modifier colis';
	const DELETE_PACK = 'supprimer colis';
	const EDIT_ARRI = 'modifier arrivage';
	const DELETE_ARRI = 'supprimer arrivage';

	// menu qualité
	const DISPLAY_LITI = 'afficher litiges';
	const TREAT_LITIGE = 'traiter les litiges';

	// menu demande
	const DISPLAY_DEM_COLL = 'afficher collectes';
	const DISPLAY_DEM_LIVR = 'afficher livraisons';
	const DISPLAY_HAND = 'afficher services';
    const DISPLAY_ACHE = 'afficher acheminements';
    const CREATE_ACHE = 'créer acheminements';
    const EDIT_DRAFT_DISPATCH = 'modifier acheminements brouillons';
    const EDIT_UNPROCESSED_DISPATCH = 'modifier acheminements à traiter';
    const EDIT_PROCESSED_DISPATCH = 'modifier acheminements traités';
    const DELETE_DRAFT_DISPATCH = 'supprimer acheminements brouillons';
    const DELETE_UNPROCESSED_DISPATCH = 'supprimer acheminements à traiter';
    const DELETE_PROCESSED_DISPATCH = 'supprimer acheminements traités';

	// menu ordre
	const DISPLAY_ORDRE_COLL = 'afficher collectes';
	const DISPLAY_ORDRE_LIVR = 'afficher livraisons';
	const DISPLAY_PREPA = 'afficher préparations';
	const DISPLAY_RECE = 'afficher réceptions';
	const CREATE_REF_FROM_RECEP = 'création référence depuis réception';

	// menu stock
	const DISPLAY_ARTI = 'afficher articles';
	const DISPLAY_REFE = 'afficher références';
	const DISPLAY_ARTI_FOUR = 'afficher articles fournisseurs';
	const DISPLAY_MOUV_STOC = 'afficher mouvements de stock';
	const DISPLAY_INVE = 'afficher inventaires';
	const DISPLAY_ALER = 'afficher alertes';
	const INVENTORY_MANAGER = "gestionnaire d'inventaire";

	// menu référentiel
	const DISPLAY_FOUR = 'afficher fournisseurs';
	const DISPLAY_EMPL = 'afficher emplacements';
	const DISPLAY_CHAU = 'afficher chauffeurs';
	const DISPLAY_TRAN = 'afficher transporteurs';

	// menu paramétrage
	const DISPLAY_GLOB = 'afficher paramétrage global';
	const DISPLAY_ROLE = 'afficher rôles';
	const DISPLAY_UTIL = 'afficher utilisateurs';
	const DISPLAY_CL = 'afficher champs libres';
	const DISPLAY_EXPO = 'afficher exports';
	const DISPLAY_TYPE = 'afficher types';
	const DISPLAY_STATU_LITI = 'afficher statuts litiges';
	const DISPLAY_NATU_COLI = 'afficher nature colis';
	const DISPLAY_CF = 'afficher champs fixes';
	const DISPLAY_IMPORT = 'afficher import et mise à jour';

	// menu nomade
    const MODULE_ACCESS_STOCK = 'Accès Stock';
    const MODULE_ACCESS_TRACA = 'Accès Traçabilité';
    const MODULE_ACCESS_HAND = 'Accès Demande';
    const DEMO_MODE = 'Mode découverte';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $label;

    /**
     * @ORM\ManyToOne(targetEntity="Menu", inversedBy="actions")
     */
    private $menu;

    /**
     * @ORM\ManyToMany(targetEntity="Role", inversedBy="actions")
     */
    private $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
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
     * @return Collection|Role[]
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRole(Role $role): self
    {
        if ($this->roles->contains($role)) {
            $this->roles->removeElement($role);
        }

        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): self
    {
        $this->menu = $menu;

        return $this;
    }
}
