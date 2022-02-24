<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\SettingRepository')]
class Setting {

    const DEFAULT_WEBSITE_LOGO_VALUE = '/img/followGTwhite.svg';
    const DEFAULT_EMAIL_LOGO_VALUE = '/img/gtlogistics.jpg';
    const DEFAULT_MOBILE_LOGO_LOGIN_VALUE = '/img/mobile_logo_login.svg';
    const DEFAULT_MOBILE_LOGO_HEADER_VALUE = '/img/mobile_logo_header.svg';
    //temporary settings
    const APP_CLIENT = "APP_CLIENT";
    // arrivages
    const REDIRECT_AFTER_NEW_ARRIVAL = "REDIRECT_AFTER_NEW_ARRIVAL";
    const SEND_MAIL_AFTER_NEW_ARRIVAL = "SEND_MAIL_AFTER_NEW_ARRIVAL";
    const AUTO_PRINT_COLIS = "AUTO_PRINT_COLIS";
    const PRINT_TWICE_CUSTOMS = "PRINT_TWICE_CUSTOMS";
    const DROP_OFF_LOCATION_IF_CUSTOMS = 'DROP_OFF_LOCATION_IF_CUSTOMS';
    const DROP_OFF_LOCATION_IF_EMERGENCY = 'DROP_OFF_LOCATION_IF_EMERGENCY';
    const ARRIVAL_EMERGENCY_TRIGGERING_FIELDS = 'ARRIVAL_EMERGENCY_TRIGGERING_FIELDS';
    // mvt traca
    const CLOSE_AND_CLEAR_AFTER_NEW_MVT = 'CLOSE_AND_CLEAR_AFTER_NEW_MVT';
    const SEND_PACK_DELIVERY_REMIND = 'SEND_PACK_DELIVERY_REMIND';
    // réceptions
    const DEFAULT_LOCATION_RECEPTION = "DEFAULT_LOCATION_RECEPTION";
    // livraisons
    const DEFAULT_LOCATION_LIVRAISON = "DEFAULT_LOCATION_LIVRAISON";
    const CREATE_DL_AFTER_RECEPTION = "CREATION_DL_APRES_RECEPTION";
    const CREATE_PREPA_AFTER_DL = 'CREATE_PREPA_AFTER_DL';
    const REQUESTER_IN_DELIVERY = 'REQUESTER_IN_DELIVERY';
    const DISPLAY_PICKING_LOCATION = 'DISPLAY_PICKING_LOCATION';
    const MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST = "MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST";
    // collectes
    const MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST = 'MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST';
    // services
    const REMOVE_HOURS_DATETIME = 'REMOVE_HOURS_DATETIME';
    const HANDLING_EXPECTED_DATE_COLOR_AFTER = 'HANDLING_EXPECTED_DATE_COLOR_AFTER';
    const HANDLING_EXPECTED_DATE_COLOR_D_DAY = 'HANDLING_EXPECTED_DATE_COLOR_D_DAY';
    const HANDLING_EXPECTED_DATE_COLOR_BEFORE = 'HANDLING_EXPECTED_DATE_COLOR_BEFORE';
    // stock
    const SEND_MAIL_MANAGER_WARNING_THRESHOLD = 'SEND_MAIL_MANAGER_WARNING_THRESHOLD';
    const SEND_MAIL_MANAGER_SECURITY_THRESHOLD = 'SEND_MAIL_MANAGER_SECURITY_THRESHOLD';
    const STOCK_EXPIRATION_DELAY = 'STOCK_EXPIRATION_DELAY';
    const DEFAULT_LOCATION_REFERENCE = 'DEFAULT_LOCATION_REFERENCE';
    // tableaux de bord
    const MVT_DEPOSE_DESTINATION = "MVT_DEPOSE_DESTINATION";
    const OVERCONSUMPTION_LOGO = 'OVERCONSUMPTION_LOGO';
    const PREFILL_DUE_DATE_TODAY = 'PREFILL_DUE_DATE_TODAY';
    const PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER = 'PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER';
    const PACK_MUST_BE_NEW = 'PACK_MUST_BE_NEW';
    // apparence
    const FONT_FAMILY = 'FONT_FAMILY';
    const WEBSITE_LOGO = 'WEBSITE_LOGO';
    const EMAIL_LOGO = 'EMAIL_LOGO';
    const MOBILE_LOGO_HEADER = 'MOBILE_LOGO_HEADER';
    const MOBILE_LOGO_LOGIN = 'MOBILE_LOGO_LOGIN';
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
    // document
    const DELIVERY_NOTE_LOGO = 'DELIVERY_NOTE_LOGO';
    const WAYBILL_LOGO = 'WAYBILL_LOGO';
    // étiquettes
    const INCLUDE_BL_IN_LABEL = "INCLURE_BL_SUR_ETIQUETTE";
    const INCLUDE_QTT_IN_LABEL = "INCLURE_QTT_SUR_ETIQUETTE";
    const BARCODE_TYPE_IS_128 = 'BARCORE_TYPE';
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
    const INCLUDE_DZ_LOCATION_IN_LABEL = "INCLURE_EMPLACEMENT_DROPZONE_SUR_ETIQUETTE";
    const INCLUDE_ARRIVAL_TYPE_IN_LABEL = "INCLURE_TYPE_ARRIVAGE_SUR_ETIQUETTE";
    const INCLUDE_PACK_COUNT_IN_LABEL = "INCLURE_NOMBRE_DE_COLIS_SUR_ETIQUETTE";
    const INCLUDE_EMERGENCY_IN_LABEL = "INCLURE_URGENCE_SUR_ETIQUETTE";
    const INCLUDE_CUSTOMS_IN_LABEL = "INCLURE_DOUANE_SUR_ETIQUETTE";
    const CL_USED_IN_LABELS = "CL_USED_IN_LABELS";
    const INCLUDE_BUSINESS_UNIT_IN_LABEL = "INCLURE_BUSINESS_UNIT_SUR_ETIQUETTE";
    const QR_CODE = [
        "value" => false,
        "label" => 'QR Code',
    ];
    const CODE_128 = [
        "value" => true,
        "label" => 'Code 128',
    ];
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
