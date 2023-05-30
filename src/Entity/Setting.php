<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting {

    const DEFAULT_WEBSITE_LOGO_VALUE = '/img/followGTwhite.svg';
    const DEFAULT_EMAIL_LOGO_VALUE = '/img/gtlogistics.jpg';
    const DEFAULT_MOBILE_LOGO_LOGIN_VALUE = '/img/mobile_logo_login.svg';
    const DEFAULT_MOBILE_LOGO_HEADER_VALUE = '/img/mobile_logo_header.svg';
    const DEFAULT_TOP_LEFT_VALUE = '/img/followGTblack.svg';
    const DEFAULT_LABEL_EXAMPLE_VALUE = '/img/exemple_etiquette_article.png';

    const DEFAULT_DELIVERY_WAYBILL_TEMPLATE_VALUE = 'modele/waybill/delivery_template.dotx';
    const DEFAULT_DISPATCH_WAYBILL_TEMPLATE_VALUE = 'modele/waybill/dispatch_template.dotx';
    const DEFAULT_DISPATCH_RECAP_TEMPLATE_VALUE = 'modele/recap/dispatch_template.dotx';
    const DEFAULT_DISPATCH_WAYBILL_TEMPLATE_VALUE_WITH_RUPTURE = 'modele/waybill/dispatch_arrival_template.dotx';
    const DEFAULT_DELIVERY_SLIP_TEMPLATE_VALUE = 'modele/slip/delivery_slip_template.dotx';

    //temporary settings
    const APP_CLIENT = "APP_CLIENT";

    // arrivages UL
    const REDIRECT_AFTER_NEW_ARRIVAL = "REDIRECT_AFTER_NEW_ARRIVAL";
    const SEND_MAIL_AFTER_NEW_ARRIVAL = "SEND_MAIL_AFTER_NEW_ARRIVAL";
    const AUTO_PRINT_LU = "AUTO_PRINT_LU";
    const PRINT_TWICE_CUSTOMS = "PRINT_TWICE_CUSTOMS";
    const DROP_OFF_LOCATION_IF_CUSTOMS = 'DROP_OFF_LOCATION_IF_CUSTOMS';
    const DROP_OFF_LOCATION_IF_EMERGENCY = 'DROP_OFF_LOCATION_IF_EMERGENCY';
    const ARRIVAL_EMERGENCY_TRIGGERING_FIELDS = 'ARRIVAL_EMERGENCY_TRIGGERING_FIELDS';
    const USE_TRUCK_ARRIVALS = 'USE_TRUCK_ARRIVALS';

    // arrivages camion
    const TRUCK_ARRIVALS_DEFAULT_UNLOADING_LOCATION = "TRUCK_ARRIVALS_DEFAULT_UNLOADING_LOCATION";
    const TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_START = "TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_START";
    const TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_END = "TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_END";
    const TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_START = "TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_START";
    const TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_END = "TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_END";

    // association BR
    const BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL = "BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL";
    const BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM = "BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM";

    // mvt traca
    const CLOSE_AND_CLEAR_AFTER_NEW_MVT = 'CLOSE_AND_CLEAR_AFTER_NEW_MVT';
    const SEND_PACK_DELIVERY_REMIND = 'SEND_PACK_DELIVERY_REMIND';

    // réceptions
    const DEFAULT_LOCATION_RECEPTION = "DEFAULT_LOCATION_RECEPTION";

    // livraisons
    const DEFAULT_LOCATION_LIVRAISON = "DEFAULT_LOCATION_LIVRAISON";
    const CREATE_DL_AFTER_RECEPTION = "CREATION_DL_APRES_RECEPTION";
    const CREATE_PREPA_AFTER_DL = 'CREATE_PREPA_AFTER_DL';
    const DIRECT_DELIVERY = 'DIRECT_DELIVERY';
    const CREATE_DELIVERY_ONLY = 'CREATE_DELIVERY_ONLY';
    const REQUESTER_IN_DELIVERY = 'REQUESTER_IN_DELIVERY';
    const DISPLAY_PICKING_LOCATION = 'DISPLAY_PICKING_LOCATION';
    const DELIVERY_REQUEST_ADD_UL = 'DELIVERY_REQUEST_ADD_UL';
    const DELIVERY_REQUEST_REF_COMMENT_WITH_PROJECT = 'DELIVERY_REQUEST_REF_COMMENT_WITH_PROJECT';
    const DELIVERY_REQUEST_REF_COMMENT_WITHOUT_PROJECT = 'DELIVERY_REQUEST_REF_COMMENT_WITHOUT_PROJECT';
    const MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST = "MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST";
    const SET_PREPARED_UPON_DELIVERY_VALIDATION = "SET_PREPARED_UPON_DELIVERY_VALIDATION";
    const MANAGE_PREPARATIONS_WITH_PLANNING = "MANAGE_PREPARATIONS_WITH_PLANNING";
    const RECEIVER_EQUALS_REQUESTER = "RECEIVER_EQUALS_REQUESTER";
    const MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY = "MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY";

    // collectes
    const MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST = 'MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST';

    // expeditions
    const SHIPPING_REFERENCE_DEFAULT_TYPE = 'SHIPPING_REFERENCE_DEFAULT_TYPE';
    const SHIPPING_SUPPLIER_LABEL_REFERENCE_CREATE = 'SHIPPING_SUPPLIER_LABEL_REFERENCE_CREATE';
    const SHIPPING_SUPPLIER_REFERENCE_CREATE = 'SHIPPING_SUPPLIER_REFERENCE_CREATE';
    const SHIPPING_REF_ARTICLE_SUPPLIER_EQUALS_REFERENCE = 'SHIPPING_REF_ARTICLE_SUPPLIER_EQUALS_REFERENCE';
    const SHIPPING_ARTICLE_SUPPLIER_LABEL_EQUALS_REFERENCE_LABEL = 'SHIPPING_ARTICLE_SUPPLIER_LABEL_EQUALS_REFERENCE_LABEL';

    const SHIPPING_LOCATION_FROM = 'SHIPPING_LOCATION_FROM';
    const SHIPPING_LOCATION_TO = 'SHIPPING_LOCATION_TO';

    const SHIPPING_TO_TREAT_SEND_TO_REQUESTER = 'SHIPPING_TO_TREAT_SEND_TO_REQUESTER';
    const SHIPPING_TO_TREAT_SEND_TO_USER_WITH_ROLES = 'SHIPPING_TO_TREAT_SEND_TO_USER_WITH_ROLES';
    const SHIPPING_TO_TREAT_SEND_TO_ROLES = 'SHIPPING_TO_TREAT_SEND_TO_ROLES';
    const SHIPPING_SHIPPED_SEND_TO_REQUESTER = 'SHIPPING_SHIPPED_SEND_TO_REQUESTER';
    const SHIPPING_SHIPPED_SEND_TO_USER_WITH_ROLES = 'SHIPPING_SHIPPED_SEND_TO_USER_WITH_ROLES';
    const SHIPPING_SHIPPED_SEND_TO_ROLES = 'SHIPPING_SHIPPED_SEND_TO_ROLES';

    // services
    const REMOVE_HOURS_DATETIME = 'REMOVE_HOURS_DATETIME';
    const KEEP_HANDLING_MODAL_OPEN = 'KEEP_HANDLING_MODAL_OPEN';
    const PREFILL_SERVICE_DATE_TODAY = 'PREFILL_SERVICE_DATE_TODAY';
    const HANDLING_EXPECTED_DATE_COLOR_AFTER = 'HANDLING_EXPECTED_DATE_COLOR_AFTER';
    const HANDLING_EXPECTED_DATE_COLOR_D_DAY = 'HANDLING_EXPECTED_DATE_COLOR_D_DAY';
    const HANDLING_EXPECTED_DATE_COLOR_BEFORE = 'HANDLING_EXPECTED_DATE_COLOR_BEFORE';

    // stock
    const SEND_MAIL_MANAGER_WARNING_THRESHOLD = 'SEND_MAIL_MANAGER_WARNING_THRESHOLD';
    const SEND_MAIL_MANAGER_SECURITY_THRESHOLD = 'SEND_MAIL_MANAGER_SECURITY_THRESHOLD';
    const STOCK_EXPIRATION_DELAY = 'STOCK_EXPIRATION_DELAY';
    const DEFAULT_LOCATION_REFERENCE = 'DEFAULT_LOCATION_REFERENCE';
    const REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES = 'REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES';

    // stock - articles
    const ARTICLE_TYPE = 'ARTICLE_TYPE';
    const ARTICLE_REFERENCE = 'ARTICLE_REFERENCE';
    const ARTICLE_SUPPLIER = 'ARTICLE_SUPPLIER';
    const ARTICLE_SUPPLIER_REFERENCE = 'ARTICLE_SUPPLIER_REFERENCE';
    const ARTICLE_LABEL = 'ARTICLE_LABEL';
    const ARTICLE_QUANTITY = 'ARTICLE_QUANTITY';
    const ARTICLE_LOCATION = 'ARTICLE_LOCATION';

    // borne tactile - général
    const FILE_TOP_LEFT_LOGO = 'FILE_TOP_LEFT_LOGO';
    const FILE_TOP_RIGHT_LOGO = 'FILE_TOP_RIGHT_LOGO';
    const FILE_LABEL_EXAMPLE_LOGO = 'FILE_LABEL_EXAMPLE_LOGO';
    const WELCOME_MESSAGE = 'WELCOME_MESSAGE';
    const INFORMATION_MESSAGE = 'INFORMATION_MESSAGE';
    const SCAN_ARTICLE_LABEL_MESSAGE = 'SCAN_ARTICLE_LABEL_MESSAGE';
    const VALIDATION_REFERENCE_ENTRY_MESSAGE = 'VALIDATION_REFERENCE_ENTRY_MESSAGE';
    const VALIDATION_ARTICLE_ENTRY_MESSAGE = 'VALIDATION_ARTICLE_ENTRY_MESSAGE';
    const QUANTITY_ERROR_MESSAGE = 'QUANTITY_ERROR_MESSAGE';

    // borne tactile - création d'une référence (gestion quantité par article)
    const TYPE_REFERENCE_CREATE = 'TYPE_REFERENCE_CREATE';
    const FREE_FIELD_REFERENCE_CREATE = 'FREE_FIELD_REFERENCE_CREATE';
    const STATUT_REFERENCE_CREATE = 'STATUT_REFERENCE_CREATE';
    const VISIBILITY_GROUP_REFERENCE_CREATE = 'VISIBILITY_GROUP_REFERENCE_CREATE';
    const INVENTORIES_CATEGORY_REFERENCE_CREATE = 'INVENTORIES_CATEGORY_REFERENCE_CREATE';
    const FOURNISSEUR_LABEL_REFERENCE_CREATE = 'FOURNISSEUR_LABEL_REFERENCE_CREATE';
    const FOURNISSEUR_REFERENCE_CREATE = 'FOURNISSEUR_REFERENCE_CREATE';

    // borne tactile - demande de collect
    const COLLECT_REQUEST_TYPE = 'COLLECT_REQUEST_TYPE';
    const COLLECT_REQUEST_REQUESTER = 'COLLECT_REQUEST_REQUESTER';
    const COLLECT_REQUEST_OBJECT = 'COLLECT_REQUEST_OBJECT';
    const COLLECT_REQUEST_POINT_COLLECT = 'COLLECT_REQUEST_POINT_COLLECT';
    const COLLECT_REQUEST_DESTINATION = 'COLLECT_REQUEST_DESTINATION';
    const COLLECT_REQUEST_ARTICLE_QUANTITY_TO_COLLECT = 'COLLECT_REQUEST_ARTICLE_QUANTITY_TO_COLLECT';

    // borne tactile - imprimante
    const PRINTER_NAME = 'PRINTER_NAME';
    const PRINTER_SERIAL_NUMBER = 'PRINTER_SERIAL_NUMBER';
    const PRINTER_LABEL_WIDTH = 'PRINTER_LABEL_WIDTH';
    const PRINTER_LABEL_HEIGHT = 'PRINTER_LABEL_HEIGHT';
    const PRINTER_DPI = 'PRINTER_DPI';

    // tableaux de bord
    const MVT_DEPOSE_DESTINATION = "MVT_DEPOSE_DESTINATION";
    const FILE_OVERCONSUMPTION_LOGO = 'OVERCONSUMPTION_LOGO';
    const PREFILL_DUE_DATE_TODAY = 'PREFILL_DUE_DATE_TODAY';
    const FORCE_GROUPED_SIGNATURE = 'FORCE_GROUPED_SIGNATURE';
    const PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER = 'PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER';
    const PACK_MUST_BE_NEW = 'PACK_MUST_BE_NEW';

    // apparence
    const FONT_FAMILY = 'FONT_FAMILY';
    const FILE_WEBSITE_LOGO = 'WEBSITE_LOGO';
    const FILE_EMAIL_LOGO = 'EMAIL_LOGO';
    const FILE_MOBILE_LOGO_HEADER = 'MOBILE_LOGO_HEADER';
    const FILE_MOBILE_LOGO_LOGIN = 'MOBILE_LOGO_LOGIN';
    const MAX_SESSION_TIME = 'MAX_SESSION_TIME';

    const DEFAULT_FONT_FAMILY = self::FONT_MYRIAD;
    const FONT_MONTSERRAT = 'Montserrat';
    const FONT_TAHOMA = 'Tahoma';
    const FONT_MYRIAD = 'Myriad';

    const FONTS = [
        Setting::FONT_MONTSERRAT => Setting::FONT_MONTSERRAT,
        Setting::FONT_TAHOMA => Setting::FONT_TAHOMA,
        Setting::FONT_MYRIAD => Setting::FONT_MYRIAD,
    ];

    // dispatches
    const DISPATCH_WAYBILL_CARRIER = 'DISPATCH_WAYBILL_CARRIER';
    const DISPATCH_WAYBILL_CONSIGNER = 'DISPATCH_WAYBILL_CONSIGNER';
    const DISPATCH_WAYBILL_RECEIVER = 'DISPATCH_WAYBILL_RECEIVER';
    const DISPATCH_WAYBILL_LOCATION_TO = 'DISPATCH_WAYBILL_LOCATION_TO';
    const DISPATCH_WAYBILL_LOCATION_FROM = 'DISPATCH_WAYBILL_LOCATION_FROM';
    const DISPATCH_WAYBILL_CONTACT_NAME = 'DISPATCH_WAYBILL_CONTACT_NAME';
    const DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL = 'DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL';
    const DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS = "DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS";
    const DISPATCH_EXPECTED_DATE_COLOR_AFTER = 'DISPATCH_EXPECTED_DATE_COLOR_AFTER';
    const DISPATCH_EXPECTED_DATE_COLOR_D_DAY = 'DISPATCH_EXPECTED_DATE_COLOR_D_DAY';
    const DISPATCH_EXPECTED_DATE_COLOR_BEFORE = 'DISPATCH_EXPECTED_DATE_COLOR_BEFORE';

    const DISPATCH_WAYBILL_TYPE_TO_USE = 'DISPATCH_WAYBILL_TYPE_TO_USE';
    const DISPATCH_WAYBILL_TYPE_TO_USE_STANDARD = 'STANDARD';
    const DISPATCH_WAYBILL_TYPE_TO_USE_RUPTURE = 'RUPTURE';

    const DELIVERY_WAYBILL_CARRIER = 'DELIVERY_WAYBILL_CARRIER';
    const DELIVERY_WAYBILL_CONSIGNER = 'DELIVERY_WAYBILL_CONSIGNER';
    const DELIVERY_WAYBILL_RECEIVER = 'DELIVERY_WAYBILL_RECEIVER';
    const DELIVERY_WAYBILL_LOCATION_TO = 'DELIVERY_WAYBILL_LOCATION_TO';
    const DELIVERY_WAYBILL_LOCATION_FROM = 'DELIVERY_WAYBILL_LOCATION_FROM';
    const DELIVERY_WAYBILL_CONTACT_NAME = 'DELIVERY_WAYBILL_CONTACT_NAME';
    const DELIVERY_WAYBILL_CONTACT_PHONE_OR_MAIL = 'DELIVERY_WAYBILL_CONTACT_PHONE_OR_MAIL';

    const DISPATCH_NEW_REFERENCE_TYPE = 'DISPATCH_NEW_REFERENCE_TYPE';
    const DISPATCH_NEW_REFERENCE_STATUS = 'DISPATCH_NEW_REFERENCE_STATUS';
    const DISPATCH_NEW_REFERENCE_QUANTITY_MANAGEMENT = 'DISPATCH_NEW_REFERENCE_QUANTITY_MANAGEMENT';

    // mobile configuration
    const TRANSFER_TO_TREAT_SKIP_VALIDATIONS = 'TRANSFER_TO_TREAT_SKIP_VALIDATIONS';
    const MANUAL_TRANSFER_TO_TREAT_SKIP_VALIDATIONS = 'MANUAL_TRANSFER_TO_TREAT_SKIP_VALIDATIONS';
    const PREPARATION_SKIP_VALIDATIONS = 'PREPARATION_SKIP_VALIDATIONS';
    const PREPARATION_SKIP_QUANTITIES = 'PREPARATION_SKIP_QUANTITIES';
    const LIVRAISON_SKIP_VALIDATIONS = 'LIVRAISON_SKIP_VALIDATIONS';
    const LIVRAISON_SKIP_QUANTITIES = 'LIVRAISON_SKIP_QUANTITIES';
    const TRANSFER_DISPLAY_REFERENCES_ON_CARDS = 'TRANSFER_DISPLAY_REFERENCES_ON_CARDS';
    const TRANSFER_FREE_DROP = 'TRANSFER_FREE_DROP';
    const PREPARATION_DISPLAY_ARTICLES_WITHOUT_MANUAL = 'PREPARATION_DISPLAY_ARTICLES_WITHOUT_MANUAL';
    const MANUAL_DELIVERY_DISABLE_VALIDATIONS = 'MANUAL_DELIVERY_DISABLE_VALIDATIONS';
    const DELIVERY_EXPECTED_DATE_COLOR_AFTER = 'DELIVERY_EXPECTED_DATE_COLOR_AFTER';
    const DELIVERY_EXPECTED_DATE_COLOR_D_DAY = 'DELIVERY_EXPECTED_DATE_COLOR_D_DAY';
    const DELIVERY_EXPECTED_DATE_COLOR_BEFORE = 'DELIVERY_EXPECTED_DATE_COLOR_BEFORE';
    const ALLOWED_DROP_ON_FREE_LOCATION = "ALLOWED_DROP_ON_FREE_LOCATION";
    const DISPLAY_REFERENCE_CODE_AND_SCANNABLE = "DISPLAY_REFERENCE_CODE_AND_SCANNABLE";

    // document
    const DELIVERY_NOTE_LOGO = 'DELIVERY_NOTE_LOGO';


    // TODO WIIS-8882
    const FILE_WAYBILL_LOGO = 'WAYBILL_LOGO';

    // étiquettes
    const INCLUDE_BL_IN_LABEL = "INCLURE_BL_SUR_ETIQUETTE";
    const INCLUDE_QTT_IN_LABEL = "INCLURE_QTT_SUR_ETIQUETTE";
    const BARCODE_TYPE_IS_128 = 'BARCODE_TYPE';
    const LABEL_WIDTH = 'LABEL_WIDTH';
    const LABEL_HEIGHT = 'LABEL_HEIGHT';
    const LABEL_LOGO = 'LABEL_LOGO';
    const EMERGENCY_ICON = "FILE_FOR_EMERGENCY_ICON";
    const CUSTOM_ICON = "FILE_FOR_CUSTOM_ICON";
    const CUSTOM_TEXT_LABEL = "TEXT_FOR_CUSTOM_IN_LABEL";
    const EMERGENCY_TEXT_LABEL = "TEXT_FOR_EMERGENCY_IN_LABEL";
    const INCLUDE_RECIPIENT_IN_LABEL = "INCLURE_DESTINATAIRE_SUR_ETIQUETTE";
    const INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL = "INCLURE_COMMANDE_ET_NUMERO_DE_PROJET_SUR_ETIQUETTE";

    const INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL = "INCLURE_EMPLACEMENT_DESTINATION_SUR_ETIQUETTE_ARTICLE_RECEPTION";
    const INCLUDE_RECIPIENT_IN_ARTICLE_LABEL = "INCLURE_DESTINATAIRE_SUR_ETIQUETTE_ARTICLE_RECEPTION";
    const INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL = "INCLURE_DROPZONE_DESTINATAIRE_SUR_ETIQUETTE_ARTICLE_RECEPTION";
    const INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL = "INCLURE_NUMERO_DE_LOT_SUR_ETIQUETTE_ARTICLE_RECEPTION";
    const INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL = "INCLURE_DATE_EXPIRATION_SUR_ETIQUETTE_ARTICLE_RECEPTION";
    const INCLUDE_STOCK_ENTRY_DATE_IN_ARTICLE_LABEL = "INCLURE_DATE_ENTREE_STOCK_SUR_ETIQUETTE_ARTICLE_RECEPTION";

    const INCLUDE_DZ_LOCATION_IN_LABEL = "INCLURE_EMPLACEMENT_DROPZONE_SUR_ETIQUETTE";
    const INCLUDE_ARRIVAL_TYPE_IN_LABEL = "INCLURE_TYPE_ARRIVAGE_SUR_ETIQUETTE";
    const INCLUDE_PACK_COUNT_IN_LABEL = "INCLURE_NOMBRE_DE_UL_SUR_ETIQUETTE";
    const INCLUDE_EMERGENCY_IN_LABEL = "INCLURE_URGENCE_SUR_ETIQUETTE";
    const INCLUDE_CUSTOMS_IN_LABEL = "INCLURE_DOUANE_SUR_ETIQUETTE";
    const CL_USED_IN_LABELS = "CL_USED_IN_LABELS";
    const INCLUDE_BUSINESS_UNIT_IN_LABEL = "INCLURE_BUSINESS_UNIT_SUR_ETIQUETTE";
    const INCLUDE_PROJECT_IN_LABEL = "INCLUDE_PROJECT_IN_LABEL";

    // modèles de document
    const DEFAULT_DELIVERY_WAYBILL_TEMPLATE = "DEFAULT_DELIVERY_WAYBILL_TEMPLATE";
    const CUSTOM_DELIVERY_WAYBILL_TEMPLATE = "CUSTOM_DELIVERY_WAYBILL_TEMPLATE";
    const CUSTOM_DELIVERY_WAYBILL_TEMPLATE_FILE_NAME = "CUSTOM_DELIVERY_WAYBILL_TEMPLATE_FILE_NAME";

    const DEFAULT_DISPATCH_WAYBILL_TEMPLATE = "DEFAULT_DISPATCH_WAYBILL_TEMPLATE";
    const CUSTOM_DISPATCH_WAYBILL_TEMPLATE = "CUSTOM_DISPATCH_WAYBILL_TEMPLATE";
    const CUSTOM_DISPATCH_WAYBILL_TEMPLATE_FILE_NAME = "CUSTOM_DISPATCH_WAYBILL_TEMPLATE_FILE_NAME";

    const DEFAULT_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE = "DEFAULT_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE";
    const CUSTOM_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE = "CUSTOM_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE";
    const CUSTOM_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE_FILE_NAME = "CUSTOM_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE_FILE_NAME";

    const DEFAULT_DISPATCH_RECAP_TEMPLATE = "DEFAULT_DISPATCH_RECAP_TEMPLATE";
    const CUSTOM_DISPATCH_RECAP_TEMPLATE = "CUSTOM_DISPATCH_RECAP_TEMPLATE";
    const CUSTOM_DISPATCH_RECAP_TEMPLATE_FILE_NAME = "CUSTOM_DISPATCH_RECAP_TEMPLATE_FILE_NAME";

    const DEFAULT_DELIVERY_SLIP_TEMPLATE = "DEFAULT_DELIVERY_SLIP_TEMPLATE";
    const CUSTOM_DELIVERY_SLIP_TEMPLATE = "CUSTOM_DELIVERY_SLIP_TEMPLATE";
    const CUSTOM_DELIVERY_SLIP_TEMPLATE_FILE_NAME = "CUSTOM_DELIVERY_SLIP_TEMPLATE_FILE_NAME";

    const QR_CODE = [
        "value" => false,
        "label" => 'QR Code',
    ];
    const CODE_128 = [
        "value" => true,
        "label" => 'Code 128',
    ];
    // Stock inventaire
    const RFID_PREFIX = "RFID_PREFIX";
    const RFID_KPI_MIN = "RFID_KPI_MIN";
    const RFID_KPI_MAX = "RFID_KPI_MAX";

    // export csv
    const USES_UTF8 = 'USES_UTF8';
    const ENCODING_VALUES = [
        0 => self::ENCODAGE_EUW ['label'],
        1 => self::ENCODAGE_UTF8['label'],
    ];
    const ENCODAGE_UTF8 = [
        'value' => true,
        'label' => "UTF-8",
    ];
    const ENCODAGE_EUW = [
        'value' => false,
        'label' => "1252 Europe de l'ouest Windows",
    ];

    const NON_BUSINESS_HOURS_MESSAGE = 'NON_BUSINESS_HOURS_MESSAGE';
    const SHIPMENT_NOTE_COMPANY_DETAILS = 'SHIPMENT_NOTE_COMPANY_DETAILS';
    const SHIPMENT_NOTE_SENDER_DETAILS = 'SHIPMENT_NOTE_SENDER_DETAILS';
    const SHIPMENT_NOTE_ORIGINATOR = 'SHIPMENT_NOTE_ORIGINATOR';
    const FILE_SHIPMENT_NOTE_LOGO = 'FILE_SHIPMENT_NOTE_LOGO';

    const TRANSPORT_DELIVERY_REQUEST_EMERGENCIES = 'TRANSPORT_DELIVERY_REQUEST_EMERGENCIES';
    const TRANSPORT_DELIVERY_DESTINATAIRES_MAIL = 'TRANSPORT_DELIVERY_DESTINATAIRES_MAIL';

    // tournées
    const TRANSPORT_ROUND_PACK_REJECT_MOTIVES = 'TRANSPORT_ROUND_PACK_REJECT_MOTIVES';
    const TRANSPORT_ROUND_DELIVERY_REJECT_MOTIVES = 'TRANSPORT_ROUND_DELIVERY_REJECT_MOTIVES';
    const TRANSPORT_ROUND_COLLECT_REJECT_MOTIVES = 'TRANSPORT_ROUND_COLLECT_REJECT_MOTIVES';
    const TRANSPORT_ROUND_END_ROUND_LOCATIONS = 'TRANSPORT_ROUND_END_ROUND_LOCATIONS';
    const TRANSPORT_ROUND_COLLECTED_PACKS_LOCATIONS = 'TRANSPORT_ROUND_COLLECTED_PACKS_LOCATIONS';
    const TRANSPORT_ROUND_REJECTED_PACKS_LOCATIONS = 'TRANSPORT_ROUND_REJECTED_PACKS_LOCATIONS';
    const TRANSPORT_ROUND_NEEDED_NATURES_TO_DROP = 'TRANSPORT_ROUND_NEEDED_NATURES_TO_DROP';
    const TRANSPORT_ROUND_DELIVERY_AVERAGE_TIME = 'TRANSPORT_ROUND_DELIVERY_AVERAGE_TIME';
    const TRANSPORT_ROUND_COLLECT_AVERAGE_TIME = 'TRANSPORT_ROUND_COLLECT_AVERAGE_TIME';
    const TRANSPORT_ROUND_DELIVERY_COLLECT_AVERAGE_TIME = 'TRANSPORT_ROUND_DELIVERY_COLLECT_AVERAGE_TIME';
    const TRANSPORT_ROUND_KM_START_POINT = 'TRANSPORT_ROUND_KM_START_POINT';
    const TRANSPORT_ROUND_HOURLY_BILLING_START_POINT = 'TRANSPORT_ROUND_HOURLY_BILLING_START_POINT';
    const TRANSPORT_ROUND_END_POINT = 'TRANSPORT_ROUND_END_POINT';
    const TRANSPORT_ROUND_COLLECT_WORKFLOW_ENDING_MOTIVE = 'TRANSPORT_ROUND_COLLECT_WORKFLOW_ENDING_MOTIVE';

    const WAYBILL_VARIABLES = [
        "delivery" => [
            "Champs fixes livraison" => [
                "numordreliv" => "numéro de l'ordre de livraison",
                "qrcodenumordreliv" => "QR Code du numéro de l'ordre de livraison",
                "typeliv" => "type de la livraison",
                "demandeurliv" => "demandeur de la livraison",
                "projetliv" => "numéro de projet de la livraison",
            ],
            "Champs Liste des unités logistiques livraison" => [
                "UL" => "code d'une unité logistiques contenue dans la livraison",
                "nature" => "nature d'une unité logistique contenue dans la livraison",
                "quantite" => "quantité total des articles contenus dans l'unité logistique contenue dans la livraison",
                "totalquantite" => "total des quantités des articles contenus dans l'unité logistique contenue dans la livraison",
                "poids" => "poids d'une unité logistique contenue dans la livraison",
                "totalpoids" => "total du poids des unités logistiques contenues dans la livraison",
                "volume" => "volume d'une unité logistique contenue dans la livraison",
                "totalvolume" => "total du volume des unités logistiques contenues dans la livraison",
                "commentaire" => "commentaire d'une unité logistique contenue dans la livraison",
            ],
            "Champs formulaire lettre de voiture" => [
                "dateacheminement" => "date renseignée dans le champ \"Date d'acheminement\" sur le formulaire de création de lettre de voiture",
                "transporteur" => "chaîne de caractères renseignée dans le champ \"Transporteur\" sur le formulaire de création de lettre de voiture",
                "expediteur" => "chaîne de caractères renseignée dans le champ \"Expéditeur\" sur le formulaire de création de lettre de voiture",
                "destinataire" => "chaîne de caractères renseignée dans le champ \"Destinataire\" sur le formulaire de création de lettre de voiture",
                "nomexpediteur" => "chaîne de caractères renseignée dans le champ \"Nom\" sous \"Contact expéditeur\" sur le formulaire de création de lettre de voiture",
                "telemailexpediteur" => "chaîne de caractères renseignée dans le champ \"Téléphone - Email\" sous \"Contact expéditeur\" sur le formulaire de création de lettre de voiture",
                "nomdestinataire" => "chaîne de caractères renseignée dans le champ \"Nom\" sous \"Contact destinataire\" sur le formulaire de création de lettre de voiture",
                "telemaildestinataire" => "chaîne de caractères renseignée dans le champ \"Téléphone - Email\" sous \"Contact destinataire\" sur le formulaire de création de lettre de voiture",
                "note" => "chaîne de caractères renseignée dans le champ \"Note de bas de page\" sur le formulaire de création de lettre de voiture",
                "lieuchargement" => "chaîne de caractères renseignée dans le champ \"Lieu de chargement\" sur le formulaire de création de lettre de voiture",
                "lieudechargement" => "chaîne de caractères renseignée dans le champ \"Lieu de déchargement\" sur le formulaire de création de lettre de voiture",
            ],
        ],
        "dispatch" => [
            "waybill" => [
                "Champs fixes acheminement" => [
                    "numach" => "numéro de l'acheminement",
                    "qrcodenumach" => "QR Code du numéro de l'acheminement",
                    "typeach" => "type de l'acheminement",
                    "transporteurach" => "transporteur de l'acheminement",
                    "numtracktransach" => "numéro de tracking transporteur de l'acheminement",
                    "demandeurach" => "demandeur de l'acheminement",
                    "destinatairesach" => "destinataires de l'acheminement",
                    "numprojetach" => "numéro de projet de l'acheminement",
                    "numcommandeach" => "numéro de commande de l'acheminement ",
                    "date1ach" => "date d'échéance 1 de l'acheminement",
                    "date2ach" => "date d'échéance 2 de l'acheminement",
                ],
                "Champs Liste des unités logistiques livraison" => [
                    "UL" => "code d'une unité logistiques contenue dans la livraison",
                    "nature" => "nature d'une unité logistique contenue dans la livraison",
                    "quantite" => "quantité total des articles contenus dans l'unité logistique contenue dans la livraison",
                    "totalquantite" => "total des quantités des articles contenus dans l'unité logistique contenue dans la livraison",
                    "poids" => "poids d'une unité logistique contenue dans la livraison",
                    "totalpoids" => "total du poids des unités logistiques contenues dans la livraison",
                    "volume" => "volume d'une unité logistique contenue dans la livraison",
                    "totalvolume" => "total du volume des unités logistiques contenues dans la livraison",
                    "commentaire" => "commentaire d'une unité logistique contenue dans la livraison",
                ],
                "Champs formulaire lettre de voiture" => [
                    "dateacheminement" => "date renseignée dans le champ \"Date d'acheminement\" sur le formulaire de création de lettre de voiture",
                    "transporteur" => "chaîne de caractères renseignée dans le champ \"Transporteur\" sur le formulaire de création de lettre de voiture",
                    "expediteur" => "chaîne de caractères renseignée dans le champ \"Expéditeur\" sur le formulaire de création de lettre de voiture",
                    "destinataire" => "chaîne de caractères renseignée dans le champ \"Destinataire\" sur le formulaire de création de lettre de voiture",
                    "nomexpediteur" => "chaîne de caractères renseignée dans le champ \"Nom\" sous \"Contact expéditeur\" sur le formulaire de création de lettre de voiture",
                    "telemailexpediteur" => "chaîne de caractères renseignée dans le champ \"Téléphone - Email\" sous \"Contact expéditeur\" sur le formulaire de création de lettre de voiture",
                    "nomdestinataire" => "chaîne de caractères renseignée dans le champ \"Nom\" sous \"Contact destinataire\" sur le formulaire de création de lettre de voiture",
                    "telemaildestinataire" => "chaîne de caractères renseignée dans le champ \"Téléphone - Email\" sous \"Contact destinataire\" sur le formulaire de création delettre de voiture",
                    "note" => "chaîne de caractères renseignée dans le champ \"Note de bas de page\" sur le formulaire de création de lettre de voiture",
                    "lieuchargement" => "chaîne de caractères renseignée dans le champ \"Lieu de chargement\" sur le formulaire de création de lettre de voiture",
                    "lieudechargement" => "chaîne de caractères renseignée dans le champ \"Lieu de déchargement\" sur le formulaire de création de lettre de voiture",
                ],
                "Champs de l'arrivage de provenance des unités logistiques" => [
                    "numarrivage" => "numéro de l'arrivage dont est issue l'unité logistique",
                    "numcommandearrivage" => "numéros de commande de l'arrivage dont est issue l'unité logistique",
                    "tableauULarrivage" => "tableau contenant la liste des unités logistiques contenues dans l'arrivage de provenance. Le tableau contient les colonnes : Unité de tracking, Nature, Quantité, Poids et Numero commande arrivage.",
                ],
            ],
            "report" => [
                "Champs fixes acheminement" => [
                    "titredocument" => "titre du document",
                    "numach" => "numéro de l'acheminement",
                    "qrcodenumach" => "QR Code du numéro de l'acheminement",
                    "typeach" => "type de l'acheminement",
                    "statutach" => "statut de l'acheminement",
                    "emppriseach" => "emplacement de prise de l'acheminement",
                    "empdeposeach" => "emplacement de dépose de l'acheminement",
                    "transporteurach" => "transporteur de l'acheminement",
                    "numtracktransach" => "numéro de tracking transporteur de l'acheminement",
                    "demandeurach" => "demandeur de l'acheminement",
                    "signataireach" => "signataire de l'acheminement",
                    "destinatairesach" => "destinataires de l'acheminement",
                    "numprojetach" => "numéro de projet de l'acheminement",
                    "numcommandeach" => "numéro de commande de l'acheminement ",
                    "date1ach" => "date d'échéance 1 de l'acheminement",
                    "date2ach" => "date d'échéance 2 de l'acheminement",
                    "urgenceach" => "urgence de l'acheminement",
                    "commentaireach" => "commentaire de l'acheminement",
                    "datestatutach" => "date et heure du changement de statut de l'acheminement",
                ],
                "Champs Liste des unités logistiques acheminements" => [
                    "UL" => "code d'une unité logistiques contenue dans l'acheminement. Il y aura autant de lignes que d'UL dans l'acheminement",
                    "natureul" => "nature d'une unité logistique contenue dans l'acheminement",
                    "quantiteul" => "quantité d'une unité logistique contenue dans l'acheminement",
                ],
                "Champs Liste des références dans UL" => [
                    "referenceref" => "référence contenue dans l'UL",
                    "quantiteref" => "quantité d'une référence contenue dans l'UL",
                    "materielhorsformatref" => "matériel hors format d'une référence contenue dans l'UL",
                    "numeroserieref" => "numéro de série d'une référence contenue dans l'UL",
                    "numerolotref" => "numéro de lot d'une référence contenue dans l'UL",
                    "codefabricantref" => "code farbicant d'une référence contenue dans l'UL",
                    "numeroscelleref" => "numéro scellée d'une référence contenue dans l'UL",
                    "dimensionsref" => "dimensions d'une référence contenue dans l'UL",
                    "poidsref" => "poids d'une référence contenue dans l'UL",
                    "volumeref" => "volume d'une référence contenue dans l'UL",
                    "adrref" => "ADR présent sur une référence contenue dans l'UL",
                    "documentsref" => "documents associés d'une référence contenue dans l'UL",
                    "commentaireref" => "commentaire d'une référence contenue dans l'UL",
                    "photoref" => "photo d'une référence contenue dans l'UL",
                ],
            ],
        ],
    ];

    const SLIP_VARIABLES = [
        "shipping" => [
            "Champs bordereau de livraison" => [
                "QRCodeexpe" => "QR Code du numéro de la demande d'expédition",
                "numexpedition" => "numéro de la demande d'expédition",
                "demandeurs" => "demandeur(s) de la demande d'expédition",
                "teldemandeur" => "téléphone(s) du/des demandeurs de la demande d'expédition",
                "numcommandeclient" => "numéro de commande client sur la demande d'expédition",
                "livraisongracieux" => 'valeur du champ "Livraison à titre gracieux" - Oui/Non',
                "articlesconformes" => 'valeur du champ "Article(s) conforme(s)" - Oui/Non',
                "client" => "client de la demande d'expédition",
                "destinataire" => "destinataire chez le client de la demande d'expédition",
                "teldestinataire" => "téléphone du destinataire chez le client de la demande d'expédition",
                "adressedestinataire" => "adresse de livraison de la demande d'expédition",
                "datecreation" => "date de création de la demande d'expédition",
                "datepriseenchargesouhaitee" => "date de prise en charge souhaitée pour la demande d'expédition",
                "port" => "valeur du champ Port - Payé/Dû",
                "dateenlevement" => "date d'enlèvement planifiée des produits de la demande d'expédition",
                "dateexpedition" => "date de confirmation de l'expédition des produits",
                "reference" => "référence à expédier",
                "quantite" => "quantité de la référence à expédier",
                "prixunitaire" => "prix unitaire de la référence à expédier",
                "poidsnet" => "poids net de la référence à expédier",
                "montantotal" => "montant total de la référence à expédier",
                "matieredangereuse" => "valeur du champ matière dangereuse de la référence à expédier - oui/non",
                "FDS" => "nom de la ou des fiches de données de sécurité de la référence à expédier",
                "CodeONU" => "code ONU de la référence à expédier",
                "CodeNDP" => "code NDP de la référence à expédier",
                "classeproduit" => "classe produit de la référence à expédier",
                "poidsnettotal" => "poids net total des références à expédier sur la demande",
                "valeurtotal" => "valeur totale des références à expédier sur la demande",
                "dimensioncolis" => "dimension d'un colis à expédier sur la demande", // TODO
                "envoi" => "spécification du transport",
                "nbcolis" => "nombre de colis à expédier sur la demande",
                "poidsbruttotal" => "poids brut total des colis à expédier sur la demande",
                "nomtransporteur" => "nom du transporteur choisit pour la demande d'expédition",
                "numtracking" => "numéro de tracking communiqué par le transporteur pour la demande d'expédition",
            ],
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getValue(): ?string {
        return $this->value;
    }

    public function setValue(?string $value): self {
        $this->value = $value;

        return $this;
    }

}
