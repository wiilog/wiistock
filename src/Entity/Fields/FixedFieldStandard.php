<?php

namespace App\Entity\Fields;

use App\Repository\Fields\FixedFieldStandardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FixedFieldStandardRepository::class)]
class FixedFieldStandard extends FixedField {
    public const FIELD_TYPE = 'fixedFieldStandard';

    const ELEMENTS_TYPE_FREE = 'FREE';
    const ELEMENTS_TYPE_FREE_NUMBER = 'FREE_NUMBER';
    const ELEMENTS_TYPE_USER = 'USER_BY_TYPE';
    const ELEMENTS_RECEIVER = 'RECEIVER';
    const ELEMENTS_TYPE = 'TYPE';
    const ELEMENTS_EXPECTED_AT_BY_TYPE = 'EXPECTED_AT_BY_TYPE';
    const ELEMENTS_LOCATION_BY_TYPE = 'LOCATION_BY_TYPE';

    const ENTITY_CODE_RECEPTION = 'réception';

    const FIELD_CODE_FOURNISSEUR = 'fournisseur';
    const FIELD_CODE_NUM_COMMANDE = 'numCommande';
    const FIELD_CODE_DATE_ATTENDUE = 'dateAttendue';
    const FIELD_CODE_DATE_COMMANDE = 'dateCommande';
    const FIELD_CODE_COMMENTAIRE = 'commentaire';
    const FIELD_CODE_ATTACHMENTS = 'attachment';
    const FIELD_CODE_UTILISATEUR = 'utilisateur';
    const FIELD_CODE_TRANSPORTEUR = 'transporteur';
    const FIELD_CODE_EMPLACEMENT = 'emplacement';
    const FIELD_CODE_ANOMALIE = 'anomalie';
    const FIELD_CODE_STORAGE_LOCATION = 'storageLocation';
    const FIELD_CODE_EMERGENCY_REC = 'manualUrgent';
    const FIELD_LABEL_FOURNISSEUR = 'fournisseur';
    const FIELD_LABEL_NUM_COMMANDE = 'numéro de commande';
    const FIELD_LABEL_DATE_ATTENDUE = 'date attendue';
    const FIELD_LABEL_DATE_COMMANDE = 'date commande';
    const FIELD_LABEL_COMMENTAIRE = 'commentaire';
    const FIELD_LABEL_ATTACHMENTS = 'pièces jointes';
    const FIELD_LABEL_UTILISATEUR = 'utilisateur';
    const FIELD_LABEL_TRANSPORTEUR = 'transporteur';
    const FIELD_LABEL_EMPLACEMENT = 'emplacement';
    const FIELD_LABEL_ANOMALIE = 'anomalie';
    const FIELD_LABEL_STORAGE_LOCATION = 'emplacement de stockage';
    const FIELD_LABEL_EMERGENCY_REC = 'urgence';

    const ENTITY_CODE_ARRIVAGE = 'arrivage';
    const FIELD_CODE_PROVIDER_ARRIVAGE = 'fournisseur';
    const FIELD_CODE_CARRIER_ARRIVAGE = 'transporteur';
    const FIELD_CODE_CHAUFFEUR_ARRIVAGE = 'chauffeur';
    const FIELD_CODE_NUMERO_TRACKING_ARRIVAGE = 'noTracking';
    const FIELD_CODE_NUM_COMMANDE_ARRIVAGE = 'numeroCommandeList';
    const FIELD_CODE_DROP_LOCATION_ARRIVAGE = 'dropLocationArrival';
    const FIELD_CODE_RECEIVERS = 'receivers';
    const FIELD_CODE_BUYERS_ARRIVAGE = 'acheteurs';
    const FIELD_CODE_PRINT_ARRIVAGE = 'imprimerArrivage';
    const FIELD_CODE_COMMENTAIRE_ARRIVAGE = 'commentaire';
    const FIELD_CODE_PJ_ARRIVAGE = 'pj';
    const FIELD_CODE_CUSTOMS_ARRIVAGE = 'customs';
    const FIELD_CODE_FROZEN_ARRIVAGE = 'frozen';
    const FIELD_CODE_PROJECT_NUMBER = 'projectNumber';
    const FIELD_CODE_BUSINESS_UNIT = 'businessUnit';
    const FIELD_CODE_ARRIVAL_NUMBER = 'arrivalNumber'; // not in settings table
    const FIELD_CODE_ARRIVAL_TOTAL_WEIGHT = 'arrivalTotalWeight'; // not in settings table
    const FIELD_CODE_ARRIVAL_TYPE = 'arrivalType'; // not in settings table
    const FIELD_CODE_ARRIVAL_STATUS = 'arrivalStatus'; // not in settings table
    const FIELD_CODE_ARRIVAL_DATE = 'arrivalDate'; // not in settings table
    const FIELD_CODE_ARRIVAL_CREATOR = 'arrivalCreator'; // not in settings table
    const FIELD_CODE_PROJECT = 'project';

    const FIELD_LABEL_PROVIDER_ARRIVAGE = 'fournisseur';
    const FIELD_LABEL_CARRIER_ARRIVAGE = 'transporteur';
    const FIELD_LABEL_CHAUFFEUR_ARRIVAGE = 'chauffeur';
    const FIELD_LABEL_NUMERO_TRACKING_ARRIVAGE = 'numéro tracking transporteur';
    const FIELD_LABEL_NUM_BL_ARRIVAGE = 'n° commande / BL';
    const FIELD_LABEL_RECEIVERS = 'destinataire(s)';
    const FIELD_LABEL_BUYERS_ARRIVAGE = 'acheteurs';
    const FIELD_LABEL_PRINT_ARRIVAGE = 'imprimer arrivage';
    const FIELD_LABEL_COMMENTAIRE_ARRIVAGE = 'commentaire';
    const FIELD_LABEL_PJ_ARRIVAGE = 'Pièces jointes';
    const FIELD_LABEL_CUSTOMS_ARRIVAGE = 'douane';
    const FIELD_LABEL_FROZEN_ARRIVAGE = 'congelé';
    const FIELD_LABEL_PROJECT_NUMBER = 'numéro projet';
    const FIELD_LABEL_BUSINESS_UNIT = 'business unit';
    const FIELD_LABEL_DROP_LOCATION_ARRIVAGE = 'emplacement de dépose';
    const FIELD_LABEL_ARRIVAL_NUMBER = 'N° arrivage'; // not in settings table
    const FIELD_LABEL_ARRIVAL_TOTAL_WEIGHT = 'Poids total'; // not in settings table
    const FIELD_LABEL_ARRIVAL_TYPE = 'Type'; // not in settings table
    const FIELD_LABEL_ARRIVAL_STATUS = 'Statut'; // not in settings table
    const FIELD_LABEL_ARRIVAL_DATE = 'Date'; // not in settings table
    const FIELD_LABEL_ARRIVAL_CREATOR = 'Utilisateur'; // not in settings table
    const FIELD_LABEL_PROJECT = 'Projet';

    const ENTITY_CODE_TRUCK_ARRIVAL = 'truckArrival';
    const FIELD_CODE_TRUCK_ARRIVAL_CARRIER = 'truckCarrier';
    const FIELD_CODE_TRUCK_ARRIVAL_DRIVER = 'driver';
    const FIELD_CODE_TRUCK_ARRIVAL_REGISTRATION_NUMBER = 'registrationNumber';
    const FIELD_CODE_TRUCK_ARRIVAL_UNLOADING_LOCATION = 'unloadingLocation';
    const FIELD_LABEL_TRUCK_ARRIVAL_CARRIER = 'transporteur';
    const FIELD_LABEL_TRUCK_ARRIVAL_DRIVER = 'chauffeur';
    const FIELD_LABEL_TRUCK_ARRIVAL_REGISTRATION_NUMBER = 'immatriculation';
    const FIELD_LABEL_TRUCK_ARRIVAL_UNLOADING_LOCATION = 'emplacement de déchargement';


    const ENTITY_CODE_DISPATCH = 'acheminements';
    const FIELD_CODE_TYPE_DISPATCH = 'type';
    const FIELD_CODE_STATUS_DISPATCH = 'status';
    const FIELD_CODE_START_DATE_DISPATCH = 'startDate';
    const FIELD_CODE_END_DATE_DISPATCH = 'endDate';
    const FIELD_CODE_REQUESTER_DISPATCH = 'requester';
    const FIELD_CODE_CARRIER_DISPATCH = 'carrier';
    const FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH = 'carrierTrackingNumber';
    const FIELD_CODE_RECEIVER_DISPATCH = 'receiver';
    const FIELD_CODE_DEADLINE_DISPATCH = 'deadline';
    const FIELD_CODE_EMAILS = 'emails';
    const FIELD_CODE_EMERGENCY = 'emergency';
    const FIELD_CODE_COMMAND_NUMBER_DISPATCH = 'commandNumber';
    const FIELD_CODE_COMMENT_DISPATCH = 'comment';
    const FIELD_CODE_ATTACHMENTS_DISPATCH = 'attachments';
    const FIELD_CODE_CUSTOMER_NAME_DISPATCH = 'customerName';
    const FIELD_CODE_CUSTOMER_PHONE_DISPATCH = 'customerPhone';
    const FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH = "customerRecipient";
    const FIELD_CODE_CUSTOMER_ADDRESS_DISPATCH = "customerAddress";
    const FIELD_CODE_LOCATION_PICK = 'pickLocation';
    const FIELD_CODE_LOCATION_DROP = 'dropLocation';
    const FIELD_CODE_DESTINATION = 'destination';
    const FIELD_LABEL_REQUESTER_DISPATCH = 'demandeur';
    const FIELD_LABEL_CARRIER_DISPATCH = 'transporteur';
    const FIELD_LABEL_CARRIER_TRACKING_NUMBER_DISPATCH = 'numéro de tracking transporteur';
    const FIELD_LABEL_RECEIVER_DISPATCH = 'destinataire';
    const FIELD_LABEL_DEADLINE_DISPATCH = 'dates d\'échéances';
    const FIELD_LABEL_EMAILS_DISPATCH = 'email(s)';
    const FIELD_LABEL_EMERGENCY = 'urgence';
    const FIELD_LABEL_COMMAND_NUMBER_DISPATCH = 'numéro de commande';
    const FIELD_LABEL_COMMENT_DISPATCH = 'commentaire';
    const FIELD_LABEL_ATTACHMENTS_DISPATCH = 'pièces jointes';
    const FIELD_LABEL_CUSTOMER_NAME_DISPATCH = 'Client';
    const FIELD_LABEL_CUSTOMER_PHONE_DISPATCH = 'Téléphone client';
    const FIELD_LABEL_CUSTOMER_RECIPIENT_DISPATCH = "À l'attention de";
    const FIELD_LABEL_CUSTOMER_ADDRESS_DISPATCH = "Adresse de livraison";
    const FIELD_LABEL_LOCATION_PICK = 'emplacement de prise';
    const FIELD_LABEL_LOCATION_DROP = 'emplacement de dépose';
    const FIELD_LABEL_DESTINATION = 'destination';
    const FIELD_LABEL_CUSTOMER_NAME = 'client';
    const FIELD_LABEL_CUSTOMER_PHONE = 'téléphone client';
    const FIELD_LABEL_CUSTOMER_ADDRESS = 'adresse de livraison';
    const FIELD_LABEL_DISPATCH_TYPE = "type";

    const ENTITY_CODE_HANDLING = 'services';
    const FIELD_CODE_LOADING_ZONE = 'loadingZone';
    const FIELD_CODE_UNLOADING_ZONE = 'unloadingZone';
    const FIELD_CODE_CARRIED_OUT_OPERATION_COUNT = 'carriedOutOperationCount';
    const FIELD_CODE_RECEIVERS_HANDLING = 'receivers';
    const FIELD_LABEL_LOADING_ZONE = 'chargement';
    const FIELD_LABEL_UNLOADING_ZONE = 'déchargement';
    const FIELD_LABEL_CARRIED_OUT_OPERATION_COUNT = 'nombre d\'opération(s) réalisée(s)';

    const FIELD_CODE_OBJECT = 'object';
    const FIELD_LABEL_OBJECT  = 'objet';

    const FIELD_LABEL_RECEIVERS_HANDLING = 'destinataires';

    const ENTITY_CODE_ARTICLE = 'articles';
    const FIELD_LABEL_ARTICLE_UNIT_PRICE = 'prix unitaire';
    const FIELD_LABEL_ARTICLE_BATCH = 'lot';
    const FIELD_LABEL_ARTICLE_ANOMALY = 'anomalie';
    const FIELD_LABEL_ARTICLE_EXPIRY_DATE = 'date de péremption';
    const FIELD_LABEL_ARTICLE_COMMENT = 'commentaire';
    const FIELD_LABEL_ARTICLE_DELIVERY_NOTE_LINE = 'ligne bon de livraison';
    const FIELD_LABEL_ARTICLE_MANUFACTURED_AT = 'date de fabrication';
    const FIELD_LABEL_ARTICLE_PRODUCTION_DATE = 'date de production';
    const FIELD_LABEL_ARTICLE_PURCHASE_ORDER_LINE = "ligne commande d'achat";
    const FIELD_LABEL_ARTICLE_NATIVE_COUNTRY = "pays d'origine";

    const FIELD_CODE_ARTICLE_UNIT_PRICE = 'unitPrice';
    const FIELD_CODE_ARTICLE_BATCH = 'batch';
    const FIELD_CODE_ARTICLE_ANOMALY = 'anomaly';
    const FIELD_CODE_ARTICLE_EXPIRY_DATE = 'expiryDate';
    const FIELD_CODE_ARTICLE_COMMENT = 'comment';
    const FIELD_CODE_ARTICLE_DELIVERY_NOTE_LINE = 'deliveryNoteLine';
    const FIELD_CODE_ARTICLE_MANUFACTURED_AT = 'manufacturedAt';
    const FIELD_CODE_ARTICLE_PRODUCTION_DATE = 'productionDate';
    const FIELD_CODE_ARTICLE_PURCHASE_ORDER_LINE = "purchaseOrderLine";
    const FIELD_CODE_ARTICLE_NATIVE_COUNTRY = "nativeCountry";

    const ENTITY_CODE_DEMANDE = 'demande';
    const FIELD_CODE_EXPECTED_AT = 'expectedAt';
    const FIELD_LABEL_EXPECTED_AT = 'date attendue';
    const FIELD_CODE_RECEIVER_DEMANDE = 'receivers';
    const FIELD_LABEL_RECEIVER_DEMANDE = 'destinataire';
    const FIELD_CODE_TYPE_DEMANDE = 'type';
    const FIELD_LABEL_TYPE_DEMANDE = 'type';
    const FIELD_CODE_DESTINATION_DEMANDE = 'destinationDemande';
    const FIELD_LABEL_DESTINATION_DEMANDE = 'destination';
    const FIELD_CODE_DELIVERY_REQUEST_PROJECT = 'deliveryRequestProject';
    const FIELD_LABEL_DELIVERY_REQUEST_PROJECT = 'projet';

    const ENTITY_CODE_EMERGENCY = "urgence";
    const FIELD_CODE_EMERGENCY_BUYER = "buyer";
    const FIELD_CODE_EMERGENCY_PROVIDER = "provider";
    const FIELD_CODE_EMERGENCY_COMMAND_NUMBER = "commandNumber";
    const FIELD_CODE_EMERGENCY_POST_NUMBER= "postNumber";
    const FIELD_CODE_EMERGENCY_CARRIER_TRACKING_NUMBER = "trackingNumber";
    const FIELD_CODE_EMERGENCY_CARRIER = "emergencyCarrier";
    const FIELD_CODE_EMERGENCY_TYPE = "emergencyType";
    const FIELD_CODE_EMERGENCY_INTERNAL_ARTICLE_CODE = "internalArticleCode";
    const FIELD_CODE_EMERGENCY_SUPPLIER_ARTICLE_CODE = "supplierArticleCode";

    const FIELD_LABEL_EMERGENCY_BUYER = "acheteur";
    const FIELD_LABEL_EMERGENCY_PROVIDER = "fournisseur";
    const FIELD_LABEL_EMERGENCY_COMMAND_NUMBER = "numéro de commande";
    const FIELD_LABEL_EMERGENCY_POST_NUMBER= "numéro de poste";
    const FIELD_LABEL_EMERGENCY_CARRIER_TRACKING_NUMBER = "numéro de tracking transporteur";
    const FIELD_LABEL_EMERGENCY_CARRIER = "transporteur";
    const FIELD_LABEL_EMERGENCY_TYPE = "type d'urgence";
    const FIELD_LABEL_EMERGENCY_INTERNAL_ARTICLE_CODE = "code article interne";
    const FIELD_LABEL_EMERGENCY_SUPPLIER_ARTICLE_CODE = "code article fournisseur";

    const ENTITY_CODE_PRODUCTION = 'production';

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $requiredCreate = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $requiredEdit = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $keptInMemory = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $displayedCreate = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $displayedEdit = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $displayedFilters = null;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $fieldRequiredHidden;

    public function isRequiredCreate(): ?bool {
        return $this->requiredCreate;
    }

    public function setRequiredCreate(?bool $requiredCreate): self {
        $this->requiredCreate = $requiredCreate;

        return $this;
    }

    public function isRequiredEdit(): ?bool {
        return $this->requiredEdit;
    }

    public function setRequiredEdit(?bool $requiredEdit): self {
        $this->requiredEdit = $requiredEdit;

        return $this;
    }

    public function isKeptInMemory(): ?bool {
        return $this->keptInMemory;
    }

    public function setKeptInMemory(?bool $keptInMemory): self
    {
        $this->keptInMemory = $keptInMemory;

        return $this;
    }

    public function isDisplayedCreate(): ?bool {
        return $this->displayedCreate;
    }

    public function setDisplayedCreate(?bool $displayedCreate): self {
        $this->displayedCreate = $displayedCreate;
        return $this;
    }

    public function isDisplayedEdit(): ?bool {
        return $this->displayedEdit;
    }

    public function setDisplayedEdit(?bool $displayedEdit): self {
        $this->displayedEdit = $displayedEdit;
        return $this;
    }

    public function isDisplayedFilters(): ?bool {
        return $this->displayedFilters;
    }

    public function setDisplayedFilters(?bool $displayedFilters): self {
        $this->displayedFilters = $displayedFilters;
        return $this;
    }

    public function getFieldRequiredHidden(): ?bool {
        return $this->fieldRequiredHidden;
    }

    public function setFieldRequiredHidden(?bool $fieldRequiredHidden): self {
        $this->fieldRequiredHidden = $fieldRequiredHidden;

        return $this;
    }
}
