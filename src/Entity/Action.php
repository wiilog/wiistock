<?php

namespace App\Entity;

use App\Entity\Dashboard\Page;
use App\Repository\ActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActionRepository::class)]
class Action {

    const LIST = 'lister';
    const CREATE = 'créer';
    const EDIT = 'modifier';
    const DELETE = 'supprimer';
    const EXPORT = 'exporter';
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
    const MANAGE_PACK = 'gérer colis';
    const DELETE_PACK = 'supprimer colis';
    const EDIT_ARRI = 'modifier arrivage';
    const DELETE_ARRI = 'supprimer arrivage';
    const EMPTY_ROUND = 'autoriser la sélection du Passage à vide';
    const CREATE_ARRIVAL = 'créer arrivage';
    const CREATE_EMERGENCY = 'créer urgence';
    const CREATE_TRACKING_MOVEMENT = 'créer mouvements';
    const FULLY_EDIT_TRACKING_MOVEMENTS = "modifier l'ensemble de la modale mouvement";
    // menu qualité
    const DISPLAY_LITI = 'afficher litiges';
    const TREAT_DISPUTE = 'traiter les litiges';
    // menu demande
    const DISPLAY_TRANSFER_REQ = 'afficher transferts';
    const DISPLAY_DEM_LIVR = 'afficher livraisons';
    const DISPLAY_DEM_COLL = 'afficher collectes';
    const DISPLAY_HAND = 'afficher services';
    const TREAT_HANDLING = 'traiter services';
    const DISPLAY_ACHE = 'afficher acheminements';
    const GENERATE_OVERCONSUMPTION_BILL = 'générer un bon de surconsommation';
    const CREATE_ACHE = 'créer acheminements';
    const EDIT_DRAFT_DISPATCH = 'modifier acheminements brouillons';
    const EDIT_UNPROCESSED_DISPATCH = 'modifier acheminements à traiter';
    const EDIT_PROCESSED_DISPATCH = 'modifier acheminements traités';
    const DELETE_DRAFT_DISPATCH = 'supprimer acheminements brouillons';
    const DELETE_UNPROCESSED_DISPATCH = 'supprimer acheminements à traiter';
    const DELETE_UNPROCESSED_HANDLING = 'supprimer services à traiter';
    const SHOW_CARRIER_FIELD = 'afficher le champ transporteur';
    const GENERATE_DELIVERY_NOTE = 'générer un bon de livraison';
    const GENERATE_WAY_BILL = 'générer une lettre de voiture';
    const GENERATE_DISPATCH_BILL = "générer un bon d'acheminement";
    const DELETE_PROCESSED_DISPATCH = 'supprimer acheminements traités';
    const DELETE_PROCESSED_HANDLING = 'supprimer services traités';
    const DISPLAY_PURCHASE_REQUESTS = "afficher demandes d'achat";
    const DELETE_DRAFT_PURCHASE_REQUEST = "supprimer demandes d'achat brouillon";
    const CREATE_PURCHASE_REQUESTS = "créer demandes d'achat";
    const EDIT_ONGOING_PURCHASE_REQUESTS = "modifier demandes d'achat à traiter et en cours";
    const DELETE_TREATED_PURCHASE_REQUESTS = "supprimer demandes d'achat traitées";
    const EDIT_DRAFT_PURCHASE_REQUEST = "modifier demandes d'achat brouillon";
    const DELETE_ONGOING_PURCHASE_REQUESTS = "supprimer demandes d'achat à traiter et en cours";
    const TRACK_SENSOR = "suivre un capteur";

    const DISPLAY_TRANSPORT = 'afficher transport';
    const CREATE_TRANSPORT = 'créer transport';
    const EDIT_TRANSPORT = 'modifier transport';
    const DELETE_TRANSPORT = 'supprimer transport';
    const DISPLAY_TRANSPORT_PLANNING = 'afficher planning';
    const SCHEDULE_TRANSPORT_ROUND = 'Planifier une tournée';
    const DISPLAY_TRANSPORT_ROUND = 'afficher tournée';
    const EDIT_TRANSPORT_ROUND = 'modifier tournée';
    const DISPLAY_TRANSPORT_SUBCONTRACT = 'afficher sous-traitance';
    const EDIT_TRANSPORT_SUBCONTRACT = 'modifier sous-traitance';


    // menu ordre
    const DISPLAY_ORDRE_COLL = 'afficher collectes';
    const DISPLAY_ORDRE_LIVR = 'afficher livraisons';
    const DISPLAY_ORDRE_TRANS = 'afficher transferts';
    const DISPLAY_PREPA = 'afficher préparations';
    const DISPLAY_PREPA_PLANNING = 'afficher préparations - planning';
    const EDIT_PREPARATION_DATE = 'modifier la date de préparation';
    const DISPLAY_RECE = 'afficher réceptions';
    const CREATE_REF_FROM_RECEP = 'création référence depuis réception';
    const PAIR_SENSOR = "associer un capteur";
    // menu stock
    const DISPLAY_ARTI = 'afficher articles';
    const DISPLAY_REFE = 'afficher références';
    const DISPLAY_ARTI_FOUR = 'afficher articles fournisseurs';
    const DISPLAY_MOUV_STOC = 'afficher mouvements de stock';
    const DISPLAY_INVE = 'afficher inventaires';
    const DISPLAY_ALER = 'afficher alertes';
    const INVENTORY_MANAGER = 'gestionnaire d\'inventaire';
    const EXPORT_ALER = 'exporter alertes';
    const CREATE_DRAFT_REFERENCE = 'créer en brouillon';
    const EDIT_PARTIALLY = 'modifier partiellement';
    const REFERENCE_VALIDATOR = 'valideur des références';
    // menu référentiel
    const DISPLAY_FOUR = 'afficher fournisseurs';
    const DISPLAY_EMPL = 'afficher emplacements';
    const DISPLAY_CHAU = 'afficher chauffeurs';
    const DISPLAY_TRAN = 'afficher transporteurs';
    const DISPLAY_VEHICLE = 'afficher véhicule';
    const DISPLAY_PACK_NATURE = 'afficher nature de colis';
    // menu IOT
    const DISPLAY_SENSOR = 'afficher capteurs';
    const DISPLAY_TRIGGER = 'afficher actionneurs';
    const DISPLAY_PAIRING = 'afficher associations';
    // menu paramétrage
    const SETTINGS_GLOBAL = 'afficher paramétrage global';
    const SETTINGS_STOCK = 'afficher stock';
    const SETTINGS_TRACING = 'afficher trace';
    const SETTINGS_TRACKING = 'afficher track';
    const SETTINGS_MOBILE = 'afficher terminal mobile';
    const SETTINGS_DASHBOARDS = 'afficher dashboards';
    const SETTINGS_IOT = 'afficher iot';
    const SETTINGS_NOTIFICATIONS = 'afficher modèles de notifications';
    const SETTINGS_USERS = 'afficher utilisateurs';
    const SETTINGS_DATA = 'afficher données';
    // menu nomade
    const MODULE_ACCESS_STOCK = 'Accès Stock';
    const MODULE_ACCESS_TRACA = 'Accès Traçabilité';
    const MODULE_ACCESS_GROUP = 'Accès Groupage';
    const MODULE_ACCESS_UNGROUP = 'Accès Dégroupage';
    const MODULE_ACCESS_HAND = 'Accès Demande';
    const MODULE_NOTIFICATIONS = 'Activer les notifications';
    const MODULE_TRACK = 'Accès Track';
    const DEMO_MODE = 'Mode découverte';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'Menu', inversedBy: 'actions')]
    private ?Menu $menu = null;

    #[ORM\ManyToOne(targetEntity: SubMenu::class, inversedBy: 'actions')]
    private ?SubMenu $subMenu = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $label = null;

    #[ORM\ManyToMany(targetEntity: 'Role', inversedBy: 'actions')]
    private Collection $roles;

    #[ORM\OneToOne(targetEntity: Dashboard\Page::class, mappedBy: 'action')]
    private ?Page $dashboard = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $displayOrder = null;

    public function __construct() {
        $this->roles = new ArrayCollection();
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

    /**
     * @return Collection|Role[]
     */
    public function getRoles(): Collection {
        return $this->roles;
    }

    public function addRole(Role $role): self {
        if(!$this->roles->contains($role)) {
            $this->roles[] = $role;
            $role->addAction($this);
        }

        return $this;
    }

    public function removeRole(Role $role): self {
        if($this->roles->removeElement($role)) {
            $role->removeAction($this);
        }

        return $this;
    }

    public function getMenu(): ?Menu {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): self {
        if($this->menu && $this->menu !== $menu) {
            $this->menu->removeAction($this);
        }
        $this->menu = $menu;
        if($menu) {
            $menu->addAction($this);
        }

        return $this;
    }

    public function getSubMenu(): ?SubMenu {
        return $this->subMenu;
    }

    public function setSubMenu(?SubMenu $subMenu): self {
        if($this->subMenu && $this->subMenu !== $subMenu) {
            $this->subMenu->removeAction($this);
        }
        $this->subMenu = $subMenu;
        if($subMenu) {
            $subMenu->addAction($this);
        }

        return $this;
    }

    public function getDashboard(): ?Dashboard\Page {
        return $this->dashboard;
    }

    public function setDashboard(?Dashboard\Page $dashboard): self {
        $this->dashboard = $dashboard;
        return $this;
    }

    public function getDisplayOrder(): ?int {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): self {
        $this->displayOrder = $displayOrder;

        return $this;
    }

}
