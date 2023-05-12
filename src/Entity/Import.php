<?php

namespace App\Entity;

use App\Repository\ImportRepository;
use DateTime;
use DateTime as WiiDateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportRepository::class)]
class Import {

    const STATUS_DRAFT = 'brouillon';
    const STATUS_CANCELLED = 'annulé';
    const STATUS_IN_PROGRESS = 'en cours';
    const STATUS_FINISHED = 'terminé';
    const STATUS_PLANNED = 'planifié';
    const ENTITY_ART = 'ART';
    const ENTITY_REF = 'REF';
    const ENTITY_FOU = 'FOU';
    const ENTITY_ART_FOU = 'ART_FOU';
    const ENTITY_RECEPTION = 'RECEP';
    const ENTITY_USER = 'USER';
    const ENTITY_DELIVERY = 'DELIVERY';
    const ENTITY_LOCATION = 'LOCATION';
    const ENTITY_CUSTOMER = 'CUSTOMER';
    const ENTITY_PROJECT = 'PROJECT';
    const ENTITY_REF_LOCATION = 'REF_LOCATION';
    const ENTITY_LABEL = [
        self::ENTITY_ART => "Articles",
        self::ENTITY_REF => "Références",
        self::ENTITY_FOU => "Fournisseurs",
        self::ENTITY_RECEPTION => "Réceptions",
        self::ENTITY_ART_FOU => "Articles fournisseurs",
        self::ENTITY_USER => "Utilisateurs",
        self::ENTITY_DELIVERY => "Livraisons",
        self::ENTITY_LOCATION => "Emplacements",
        self::ENTITY_CUSTOMER => "Clients",
        self::ENTITY_PROJECT => "Projets",
        self::ENTITY_REF_LOCATION => "Quantité référence par emplacement"
    ];
    const FIELDS_NEEDED = [
        self::ENTITY_ART_FOU => [
            'référence article de référence',
            'référence fournisseur',
            'reference',
        ],
        self::ENTITY_ART => [
            'référence article de référence',
            'label',
            'emplacement',
        ],
        self::ENTITY_FOU => [
            'codeReference',
            'nom',
        ],
        self::ENTITY_REF => [
            'reference',
            'libelle',
            'type',
            'typeQuantite',
            'emplacement',
        ],
        self::ENTITY_RECEPTION => [
            'orderNumber',
            'expectedDate',
            'quantity',
            'reference',
        ],
        self::ENTITY_USER => [
            'role',
            'username',
            'email',
            'status',
        ],
        self::ENTITY_DELIVERY => [
            'destination',
            'type',
            'status',
            'articleReference',
            'quantityDelivery',
        ],
        self::ENTITY_LOCATION => [
            'name',
        ],
        self::ENTITY_CUSTOMER => [
            'name',
        ],
        self::ENTITY_PROJECT => [
            'code',
            'projectManager',
        ],
        self::ENTITY_REF_LOCATION => [
            'reference',
            'location',
            'securityQuantity',
            'conditioningQuantity',
        ],
    ];
    const FIELD_PK = [
        self::ENTITY_ART_FOU => 'reference',
        self::ENTITY_ART => 'barCode',
        self::ENTITY_FOU => 'codeReference',
        self::ENTITY_REF => 'reference',
        self::ENTITY_RECEPTION => null,
        self::ENTITY_USER => null,
        self::ENTITY_DELIVERY => null,
        self::ENTITY_LOCATION => 'name',
        self::ENTITY_CUSTOMER => 'name',
        self::ENTITY_PROJECT => 'code',
        self::ENTITY_REF_LOCATION => 'reference',
    ];

    public const IMPORT_FIELDS_TO_FIELDS_PARAM = [
        'commentaire' => 'commentaire',
        'destination' => 'emplacement',
        'fournisseur' => 'fournisseur',
        'transporteur' => 'transporteur',
    ];
    const FIELDS_ENTITY = [
        'default' => [
            'storageLocation' => 'Emplacement de stockage',
            'visibilityGroups' => 'Groupes de visibilité',
            'reference' => 'référence',
            'barCode' => 'code barre',
            'quantite' => 'quantité',
            'label' => 'libellé',
            'libelle' => 'libellé',
            'articleFournisseur' => 'article fournisseur',
            'needsMobileSync' => 'Synchronisation nomade',
            'prixUnitaire' => 'prix unitaire',
            'rfidTag' => 'tag RFID',
            'limitSecurity' => 'seuil de sécurité',
            'limitWarning' => "seuil d'alerte",
            'quantiteStock' => 'quantité en stock',
            'typeQuantite' => 'type quantité (article ou référence)',
            'codeReference' => 'Code',
            'nom' => 'Nom',
            'referenceReference' => 'référence article de référence',
            'fournisseurReference' => 'référence fournisseur',
            'emplacement' => 'emplacement',
            'catInv' => 'catégorie inventaire',
            'articleFournisseurReference' => 'Référence article fournisseur',
            'typeLabel' => 'type',
            'dateLastInventory' => 'date dernier inventaire (jj/mm/AAAA)',
            'emergencyComment' => 'commentaire urgence',
            'orderNumber' => 'numéro de commande',
            'quantity' => 'quantité',
            'batch' => 'Lot',
            'location' => 'Emplacement',
            'manualUrgent' => 'Urgence',
            'stockManagement' => 'Gestion de stock',
            'expiryDate' => 'Date de péremption (jj/mm/AAAA)',
            'stockEntryDate' => 'Date d\'entrée en stock (jj/mm/AAAA hh:MM)',
            'managers' => 'Gestionnaire(s)',
            'orderDate' => 'date commande (jj/mm/AAAA)',
            'expectedDate' => 'date attendue (jj/mm/AAAA)',
            'buyer' => 'Acheteur',
            "outFormatEquipment" => 'Materiel hors format',
            "ADR" => 'ADR',
            "manufacturerCode" => 'Code Fabriquant',
            "volume" => 'Volume (m3)',
            "weight" => 'Poids (kg)',
            "associatedDocumentTypes" => 'Type de documents associés',


            'role' => 'Rôle',
            'deliverer' => 'Livreur',
            'username' => 'Nom d\'utilisateur',
            'email' => 'Email',
            'secondaryEmail' => 'Email 2',
            'lastEmail' => 'Email 3',
            'phone' => 'Numéro de téléphone',
            'mobileLoginKey' => 'Clé de connexion nomade',
            'address' => 'Adresse',
            'deliveryTypes' => 'Types de livraison',
            'dispatchTypes' => 'Types d\'acheminement',
            'handlingTypes' => 'Types de services',
            'dropzone' => 'Dropzone',
            'visibilityGroup' => 'Groupes de visibilité',
            'status' => 'Statut',
            'quantityDelivery' => 'Quantité à livrer',
            'articleCode' => 'Code article',
            'articleReference' => 'Référence',
            'requester' => 'Demandeur',
            'signatoryCode' => 'Code Signataire',

            'targetLocationPicking' => 'Emplacement cible picking',
            'name' => 'Nom',
            'description' => 'Description',
            'dateMaxTime' => 'Délais traça HH:MM',
            'allowedPackNatures' => "Natures autorisées",
            'allowedDeliveryTypes' => 'Types de livraisons autorisés',
            'allowedCollectTypes' => 'Types de collectes autorisés',
            'isDeliveryPoint' => 'Point de livraison',
            'isOngoingVisibleOnMobile' => 'Encours visible nomade',
            'isActive' => 'Actif',
            'signatory' => 'Signataire',
            'signatories' => 'Signataires',

            'possibleCustoms' => 'Possible douane',
            'urgent' => 'Urgent',

            'fax' => 'Fax',

            'code' => 'Code',
            'projectManager' => 'Chef de projet',

            'zone' => 'Zone',

            'securityQuantity' => 'Quantité de sécurité',
            'conditioningQuantity' => 'Quantité de conditionnement',
        ],

        self::ENTITY_CUSTOMER => [
            'name' =>  'Client',
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $entity = null;

    #[ORM\OneToOne(inversedBy: 'importCsv', targetEntity: Attachment::class)]
    private ?Attachment $csvFile = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $user = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $newEntries = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $updatedEntries = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $forced;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $flash;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?DateTime $createdAt;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nbErrors = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $startDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endDate = null;

    #[ORM\OneToOne(inversedBy: 'importLog', targetEntity: Attachment::class)]
    private ?Attachment $logFile = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $columnToField = null;

    #[ORM\OneToMany(mappedBy: 'import', targetEntity: MouvementStock::class)]
    private Collection $mouvements;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $eraseData = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $recipient = null;

    public function __construct() {
        $this->createdAt = new WiiDateTime();
        $this->mouvements = new ArrayCollection();
        $this->forced = false;
        $this->flash = false;
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

    public function getEntity(): ?string {
        return $this->entity;
    }

    public function setEntity(string $entity): self {
        $this->entity = $entity;

        return $this;
    }

    public function getCsvFile(): ?Attachment {
        return $this->csvFile;
    }

    public function setCsvFile(?Attachment $csvFile): self {
        $this->csvFile = $csvFile;

        return $this;
    }

    public function getNewEntries(): ?int {
        return $this->newEntries;
    }

    public function setNewEntries(?int $newEntries): self {
        $this->newEntries = $newEntries;

        return $this;
    }

    public function getUpdatedEntries(): ?int {
        return $this->updatedEntries;
    }

    public function setUpdatedEntries(?int $updatedEntries): self {
        $this->updatedEntries = $updatedEntries;

        return $this;
    }

    public function getNbErrors(): ?int {
        return $this->nbErrors;
    }

    public function setNbErrors(?int $nbErrors): self {
        $this->nbErrors = $nbErrors;

        return $this;
    }

    public function getStartDate(): ?DateTime {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): self {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?DateTime {
        return $this->endDate;
    }

    public function setEndDate(DateTime $endDate): self {
        $this->endDate = $endDate;

        return $this;
    }

    public function getColumnToField(): ?array {
        return $this->columnToField;
    }

    public function setColumnToField(?array $columnToField): self {
        $this->columnToField = $columnToField;

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;

        return $this;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        $this->user = $user;

        return $this;
    }

    public function getLogFile(): ?Attachment {
        return $this->logFile;
    }

    public function setLogFile(?Attachment $logFile): self {
        if(isset($this->logFile)) {
            $this->logFile->setImportLog(null);
        }

        $this->logFile = $logFile;

        if(isset($this->logFile)) {
            $this->logFile->setImportLog($this);
        }

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self {
        if(!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setImport($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getImport() === $this) {
                $mouvement->setImport(null);
            }
        }

        return $this;
    }

    public function isForced(): bool {
        return $this->forced;
    }

    public function setForced(bool $forced): self {
        $this->forced = $forced;
        return $this;
    }

    public function isFlash(): bool {
        return $this->flash;
    }

    public function setFlash(bool $flash): self {
        $this->flash = $flash;
        return $this;
    }

    public function getCreateAt(): DateTime {
        return $this->createdAt;
    }

    public function isEraseData(): bool {
        return $this->eraseData;
    }

    public function setEraseData(bool $eraseData): self {
        $this->eraseData = $eraseData;
        return $this;
    }

    public function getRecipient(): ?string {
        return $this->recipient;
    }

    public function setRecipient(?string $recipient): self {
        $this->recipient = $recipient;
        return $this;
    }

}
