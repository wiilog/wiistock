<?php

namespace App\Entity;

use App\Repository\FiltreSupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FiltreSupRepository::class)]
class FiltreSup {

    const FIELD_DATE_MIN = 'dateMin';
    const FIELD_DATE_MAX = 'dateMax';
    const FIELD_DATE_EXPECTED = 'expectedDate';
    const FIELD_STATUT = 'statut';
    const FIELD_USERS = 'utilisateurs';
    const FIELD_COMMAND_LIST = 'commandList';
    const FIELD_DECLARANTS = 'declarants';
    const FIELD_CARRIERS = 'carriers';
    const FIELD_PROVIDERS = 'providers';
    const FIELD_CATEGORY = 'category';
    const FIELD_TYPE = 'type';
    const FIELD_FILE_NUMBER = 'fileNumber';
    const FIELD_CONTACT = 'contact';
    const FIELD_PROJECT = 'project';
    const FIELD_EMPLACEMENT = 'emplacement';
    const FIELD_PACK = 'UL';
    const FIELD_REFERENCE = 'reference';
    const FIELD_DEM_COLLECTE = 'demCollecte';
    const FIELD_DEMANDE = 'demande';
    const FIELD_EMERGENCY = 'emergency';
    const FIELD_EMERGENCY_MULTIPLE = 'emergencyMultiple';
    const FIELD_BUSINESS_UNIT = 'businessUnit';
    const FIELD_PROJECT_NUMBER = 'projectNumber';
    const FIELD_ARTICLE = 'article';
    const FIELD_ANOMALY = 'anomaly';
    const FIELD_RECEPTION_STRING = 'reception_string';
    const FIELD_COMMANDE = 'commande';
    const FIELD_LITIGE_ORIGIN = 'litigeOrigin';
    const FIELD_LITIGE_DISPUTE_NUMBER = 'disputeNumber';
    const FIELD_NUM_ARRIVAGE = 'numArrivage';
    const FIELD_NATURES = 'natures';
    const FIELD_CUSTOMS = 'customs';
    const FIELD_FROZEN = 'frozen';
    const FIELD_STATUS_ENTITY = 'statusEntity';
    const FIELD_MULTIPLE_TYPES = 'multipleTypes';
    const FIELD_RECEIVERS = 'receivers';
    const FIELD_OPERATORS = 'operators';
    const FIELD_REQUESTERS = 'requesters';
    const FIELD_BUYERS = 'buyers';
    const FIELD_ALERT = 'alert';
	const FIELD_DISPATCH_NUMBER = 'dispatchNumber';
    const FIELD_MANAGERS = 'managers';
    const FIELD_ROUND_NUMBER = 'roundNumber';
    const FIELD_REQUEST_NUMBER = 'requestNumber';
    const FIELD_DELIVERERS = 'deliverers';
    const FIELD_DRIVERS = 'drivers';
    const FIELD_REGISTRATION_NUMBER = 'registrationNumber';
    const FIELD_CARRIER_TRACKING_NUMBER = 'carrierTrackingNumber';
    const FIELD_TRUCK_ARRIVAL_NUMBER = 'truckArrivalNumber';
    const FIELD_CARRIER_TRACKING_NUMBER_NOT_ASSIGNED = 'carrierTrackingNumberNotAssigned';
	const PAGE_PURCHASE_REQUEST = 'rpurchase';
	const PAGE_TRANSFER_REQUEST = 'rtransfer';
	const PAGE_TRANSFER_ORDER = 'otransfer';
	const PAGE_DEM_COLLECTE = 'dcollecte';
	const PAGE_DEM_LIVRAISON = 'dlivraison';
    const PAGE_HAND = 'handling';
    const FIELD_SUBJECT = 'subject';
    const FIELD_DESTINATION = 'destination';
    const PAGE_RECEPTION = 'reception';
    const PAGE_ORDRE_COLLECTE = 'ocollecte';
    const PAGE_ORDRE_LIVRAISON = 'olivraison';
    const PAGE_PREPA = 'prépa';
    const PAGE_PACK = 'pack';
    const PAGE_ARRIVAGE = 'arrivage';
    const PAGE_MVT_STOCK = 'mvt_stock';
    const PAGE_MVT_TRACA = 'mvt_traca';
    const PAGE_DISPATCH = 'acheminement';
    const PAGE_STATUS = 'status';
    const PAGE_INV_ENTRIES = 'inv_entries';
    const PAGE_INV_MISSIONS = 'inv_missions';
    const PAGE_INV_SHOW_MISSION = 'inv_mission_show';
    const PAGE_DISPUTE = 'litige';
    const PAGE_RECEIPT_ASSOCIATION = 'receipt_association';
    const PAGE_ARTICLE = 'article';
    const PAGE_URGENCES = 'urgences';
    const PAGE_ALERTE = 'alerte';
    const PAGE_NOTIFICATIONS = 'notifications';
    const PAGE_ENCOURS = 'encours';
    const PAGE_TRANSPORT_REQUESTS = 'transportRequests';
    const PAGE_PREPARATION_PLANNING = 'preparationPlanning';
    const PAGE_TRANSPORT_ORDERS = 'transportOrders';
    const PAGE_SUBCONTRACT_ORDERS = 'subcontractOrders';
    const PAGE_TRANSPORT_ROUNDS = 'transportRounds';
    const PAGE_IMPORT = 'import';
    const PAGE_EXPORT = 'export';
    const PAGE_TRUCK_ARRIVAL = 'truckArrival';
    const FIELD_PICK_LOCATION = 'pickLocation';
    const FIELD_DROP_LOCATION = 'dropLocation';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32)]
    private ?string $field = null;

    #[ORM\Column(type: 'text')]
    private ?string $value = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'filtresSup')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Utilisateur $user = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $page = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getField(): ?string {
        return $this->field;
    }

    public function setField(string $field): self {
        $this->field = $field;

        return $this;
    }

    public function getValue(): ?string {
        return $this->value;
    }

    public function setValue(string $value): self {
        $this->value = $value;

        return $this;
    }

    public function getPage(): ?string {
        return $this->page;
    }

    public function setPage(string $page): self {
        $this->page = $page;

        return $this;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        $this->user = $user;

        return $this;
    }

}
