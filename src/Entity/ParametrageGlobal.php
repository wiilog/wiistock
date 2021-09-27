<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParametrageGlobalRepository")
 */
class ParametrageGlobal
{

    const DEFAULT_WEBSITE_LOGO_VALUE = '/img/followGTwhite.svg';
    const DEFAULT_EMAIL_LOGO_VALUE = '/img/gtlogistics.jpg';
    const DEFAULT_MOBILE_LOGO_LOGIN_VALUE = '/img/mobile_logo_login.svg';
    const DEFAULT_MOBILE_LOGO_HEADER_VALUE = '/img/mobile_logo_header.svg';
    const MAX_SESSION_TIME = 'MAX_SESSION_TIME';

	// arrivages
    const REDIRECT_AFTER_NEW_ARRIVAL = 'REDIRECT AFTER NEW ARRIVAL';
    const SEND_MAIL_AFTER_NEW_ARRIVAL = 'SEND MAIL AFTER NEW ARRIVAL';
    const AUTO_PRINT_COLIS = 'AUTO PRINT COLIS';
    const PRINT_TWICE_CUSTOMS = 'PRINT TWICE CUSTOMS';
    const DROP_OFF_LOCATION_IF_CUSTOMS = 'EMPLACEMENT DE DEPOSE SI CHAMP DOUANE COCHE';
    const DROP_OFF_LOCATION_IF_EMERGENCY = 'EMPLACEMENT DE DEPOSE SI CHAMP URGENCE COCHE';
    const ARRIVAL_EMERGENCY_TRIGGERING_FIELDS = 'ARRIVAL_EMERGENCY_TRIGGERING_FIELDS';

    // mvt traca
    const CLOSE_AND_CLEAR_AFTER_NEW_MVT = 'CLOSE AND CLEAR AFTER NEW MVT';

    // réceptions
    const DEFAULT_LOCATION_RECEPTION = 'DEFAULT LOCATION RECEPTION';

    // livraisons
    const DEFAULT_LOCATION_LIVRAISON = 'DEFAULT LOCATION LIVRAISON';
    const CREATE_DL_AFTER_RECEPTION = 'CREATION DL APRES RECEPTION';
    const CREATE_PREPA_AFTER_DL = 'CREATION PREPA APRES DL';
    const DEMANDEUR_DANS_DL = 'DEMANDEUR DANS DL';
    const MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST = 'MANAGE LOCATION DELIVERY DROPDOWN LIST';

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

    // tableaux de bord
    const MVT_DEPOSE_DESTINATION = 'MVT DEPOSE DESTINATION';
    const OVERCONSUMPTION_LOGO = 'OVERCONSUMPTION_LOGO';
    const KEEP_DISPATCH_PACK_MODAL_OPEN = 'KEEP_DISPATCH_PACK_MODAL_OPEN';
    const OPEN_DISPATCH_ADD_PACK_MODAL_ON_CREATION = 'OPEN_DISPATCH_ADD_PACK_MODAL_ON_CREATION';
    const PREFILL_DUE_DATE_TODAY = 'PREFILL_DUE_DATE_TODAY';
    const PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER = 'PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER';
    const PACK_MUST_BE_NEW = 'PACK_MUST_BE_NEW';

    // apparence
    const FONT_FAMILY = 'FONT FAMILY';
    const WEBSITE_LOGO = 'WEBSITE_LOGO';
    const EMAIL_LOGO = 'EMAIL_LOGO';
    const MOBILE_LOGO_HEADER = 'MOBILE_LOGO_HEADER';
    const MOBILE_LOGO_LOGIN = 'MOBILE_LOGO_LOGIN';
	const FONT_MONTSERRAT = 'Montserrat';
	const FONT_TAHOMA = 'Tahoma';
	const FONT_MYRIAD = 'Myriad';
	const DEFAULT_FONT_FAMILY = self::FONT_MONTSERRAT;

    // dispatches
    const DISPATCH_WAYBILL_CARRIER = 'DISPATCH_WAYBILL_CARRIER';
    const DISPATCH_WAYBILL_CONSIGNER = 'DISPATCH_WAYBILL_CONSIGNER';
    const DISPATCH_WAYBILL_RECEIVER = 'DISPATCH_WAYBILL_RECEIVER';
    const DISPATCH_WAYBILL_LOCATION_TO = 'DISPATCH_WAYBILL_LOCATION_TO';
    const DISPATCH_WAYBILL_LOCATION_FROM = 'DISPATCH_WAYBILL_LOCATION_FROM';
    const DISPATCH_WAYBILL_CONTACT_NAME = 'DISPATCH_WAYBILL_CONTACT_NAME';
    const DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL = 'DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL';
    const DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS = 'DISPATCH OVERCONSUMPTION BILL TYPE AND STATUS';
    const DISPATCH_EXPECTED_DATE_COLOR_AFTER = 'DISPATCH_EXPECTED_DATE_COLOR_AFTER';
    const DISPATCH_EXPECTED_DATE_COLOR_D_DAY = 'DISPATCH_EXPECTED_DATE_COLOR_D_DAY';
    const DISPATCH_EXPECTED_DATE_COLOR_BEFORE = 'DISPATCH_EXPECTED_DATE_COLOR_BEFORE';

    // document
    const DELIVERY_NOTE_LOGO = 'FILE DELIVERY NOTE';
    const WAYBILL_LOGO = 'FILE WAYBILL';

    // étiquettes
    const INCLUDE_BL_IN_LABEL = 'INCLURE BL SUR ETIQUETTE';
    const INCLUDE_QTT_IN_LABEL = 'INCLURE QTT SUR ETIQUETTE';
    const BARCODE_TYPE_IS_128 = 'barcode type';
    const LABEL_LOGO = 'FILE FOR LOGO';
    const EMERGENCY_ICON = 'FILE FOR EMERGENCY ICON';
    const CUSTOM_ICON = 'FILE FOR CUSTOM ICON';
    const CUSTOM_TEXT_LABEL = 'TEXT FOR CUSTOM IN LABEL';
    const EMERGENCY_TEXT_LABEL = 'TEXT FOR EMERGENCY IN LABEL';
    const INCLUDE_RECIPIENT_IN_LABEL = 'INCLURE DESTINATAIRE SUR ETIQUETTE';
    const INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL = 'INCLURE COMMANDE ET NUMERO DE PROJET SUR ETIQUETTE';

    const INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL = 'INCLURE EMPLACEMENT DESTINATION SUR ETIQUETTE ARTICLE RECEPTION';
    const INCLUDE_RECIPIENT_IN_ARTICLE_LABEL = 'INCLURE DESTINATAIRE SUR ETIQUETTE ARTICLE RECEPTION';
    const INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL = 'INCLURE DROPZONE DESTINATAIRE SUR ETIQUETTE ARTICLE RECEPTION';
    const INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL = 'INCLURE NUMERO DE LOT SUR ETIQUETTE ARTICLE RECEPTION';
    const INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL = 'INCLURE DATE EXPIRATION SUR ETIQUETTE ARTICLE RECEPTION';

    const INCLUDE_DZ_LOCATION_IN_LABEL = 'INCLURE EMPLACEMENT DROPZONE SUR ETIQUETTE';
    const INCLUDE_ARRIVAL_TYPE_IN_LABEL = 'INCLURE TYPE ARRIVAGE SUR ETIQUETTE';
    const INCLUDE_PACK_COUNT_IN_LABEL = 'INCLURE NOMBRE DE COLIS SUR ETIQUETTE';
    const INCLUDE_EMERGENCY_IN_LABEL = 'INCLURE URGENCE SUR ETIQUETTE';
    const INCLUDE_CUSTOMS_IN_LABEL = 'INCLURE DOUANE SUR ETIQUETTE';
    const LABEL_HEIGHT_DEFAULT = 30;
    const LABEL_WIDTH_DEFAULT = 50;
    const QR_CODE = [
        "value" => false,
        "label" => 'QR Code'
    ];
    const CODE_128 = [
        "value" => true,
        "label" => 'Code 128'
    ];
    const CL_USED_IN_LABELS = 'CL USED IN LABELS';

    // export csv
	const USES_UTF8 = 'utilise utf8';
    const ENCODAGE_UTF8 = [
        'value'=> true,
        'label'=> 'UTF-8'
    ];
    const ENCODAGE_EUW = [
        'value'=> false,
        'label'=> '1252 Europe de l\'ouest Windows'
    ];


	/**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $value;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

}
