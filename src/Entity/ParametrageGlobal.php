<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParametrageGlobalRepository")
 */
class ParametrageGlobal
{
	// arrivages
    const REDIRECT_AFTER_NEW_ARRIVAL = 'REDIRECT AFTER NEW ARRIVAL';
    const SEND_MAIL_AFTER_NEW_ARRIVAL = 'SEND MAIL AFTER NEW ARRIVAL';
    const AUTO_PRINT_COLIS = 'AUTO PRINT COLIS';
    const DEFAULT_STATUT_ARRIVAGE = 'DEFAULT_STATUT_ARRIVAGE';
    const BUSINESS_UNIT_VALUES = 'BUSINESS UNIT';

    // mvt traca
    const CLOSE_AND_CLEAR_AFTER_NEW_MVT = 'CLOSE AND CLEAR AFTER NEW MVT';

    // réceptions
    const DEFAULT_LOCATION_RECEPTION = 'DEFAULT LOCATION RECEPTION';

    // livraisons
    const DEFAULT_LOCATION_LIVRAISON = 'DEFAULT LOCATION LIVRAISON';
    const CREATE_DL_AFTER_RECEPTION = 'CREATION DL APRES RECEPTION';
    const CREATE_PREPA_AFTER_DL = 'CREATION PREPA APRES DL';
    const DEMANDEUR_DANS_DL = 'DEMANDEUR DANS DL';

    // tableaux de bord
    const DASHBOARD_NATURE_COLIS = 'DASHBOARD NATURE COLIS';
    const DASHBOARD_LIST_NATURES_COLIS = 'DASHBOARD LIST NATURES COLIS';
    const DASHBOARD_LOCATION_DOCK = 'DASHBOARD_LOCATION_DOCK';
    const DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK = 'DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK';
    const DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN = 'DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN';
    const DASHBOARD_LOCATION_AVAILABLE = 'DASHBOARD_LOCATION_AVAILABLE';
    const DASHBOARD_LOCATION_TO_DROP_ZONES = 'DASHBOARD_LOCATION_TO_DROP_ZONES';
    const DASHBOARD_LOCATIONS_1 = 'DASHBOARD_LOCATIONS_1';
    const DASHBOARD_LOCATIONS_2 = 'DASHBOARD_LOCATIONS_2';
    const DASHBOARD_LOCATION_LITIGES = 'DASHBOARD_LOCATION_LITIGE';
    const DASHBOARD_LOCATION_URGENCES = 'DASHBOARD_LOCATION_URGENCES';
    const DASHBOARD_CARRIER_DOCK = 'DASHBOARD_CARRIER_DOCK';
    const MVT_DEPOSE_DESTINATION = 'MVT DEPOSE DESTINATION';
    // apparence
    const FONT_FAMILY = 'FONT FAMILY';
	const FONT_MONTSERRAT = 'Montserrat';
	const FONT_TAHOMA = 'Tahoma';
	const FONT_MYRIAD = 'Myriad';
	const DEFAULT_FONT_FAMILY = self::FONT_MONTSERRAT;

	// Packaging
    const DASHBOARD_PACKAGING_1 = 'DASHBOARD_PACKAGING_1';
    const DASHBOARD_PACKAGING_2 = 'DASHBOARD_PACKAGING_2';
    const DASHBOARD_PACKAGING_3 = 'DASHBOARD_PACKAGING_3';
    const DASHBOARD_PACKAGING_4 = 'DASHBOARD_PACKAGING_4';
    const DASHBOARD_PACKAGING_5 = 'DASHBOARD_PACKAGING_5';
    const DASHBOARD_PACKAGING_6 = 'DASHBOARD_PACKAGING_6';
    const DASHBOARD_PACKAGING_7 = 'DASHBOARD_PACKAGING_7';
    const DASHBOARD_PACKAGING_8 = 'DASHBOARD_PACKAGING_8';
    const DASHBOARD_PACKAGING_RPA = 'DASHBOARD_PACKAGING_RPA';
    const DASHBOARD_PACKAGING_LITIGE = 'DASHBOARD_PACKAGING_LITIGE';
    const DASHBOARD_PACKAGING_URGENCE = 'DASHBOARD_PACKAGING_URGENCE';
    const DASHBOARD_PACKAGING_DSQR = 'DASHBOARD_PACKAGING_DSQR';
    const DASHBOARD_PACKAGING_DESTINATION_GT = 'DASHBOARD_PACKAGING_DESTINATION_GT';
    const DASHBOARD_PACKAGING_ORIGINE_GT = 'DASHBOARD_PACKAGING_ORIGINE_GT';

    // dispatches
    const DISPATCH_EMERGENCY_VALUES = 'DISPATCH EMERGENCIES';
    const DISPATCH_WAYBILL_CARRIER = 'DISPATCH_WAYBILL_CARRIER';
    const DISPATCH_WAYBILL_CONSIGNER = 'DISPATCH_WAYBILL_CONSIGNER';
    const DISPATCH_WAYBILL_RECEIVER = 'DISPATCH_WAYBILL_RECEIVER';
    const DISPATCH_WAYBILL_LOCATION_TO = 'DISPATCH_WAYBILL_LOCATION_TO';
    const DISPATCH_WAYBILL_LOCATION_FROM = 'DISPATCH_WAYBILL_LOCATION_FROM';
    const DISPATCH_BUSINESS_UNIT_VALUES = 'DISPATCH_BUSINESS_UNIT_VALUES';

    // arrival
    const ARRIVAL_BUSINESS_UNIT_VALUES = 'ARRIVAL_BUSINESS_UNIT_VALUES';


    // étiquettes
    const INCLUDE_BL_IN_LABEL = 'INCLURE BL SUR ETIQUETTE';
    const INCLUDE_QTT_IN_LABEL = 'INCLURE QTT SUR ETIQUETTE';
    const BARCODE_TYPE_IS_128 = 'barcode type';
    const FILE_FOR_LOGO = 'FILE FOR LOGO';
    const INCLUDE_RECIPIENT_IN_LABEL = 'INCLURE DESTINATAIRE SUR ETIQUETTE';
    const INCLUDE_DZ_LOCATION_IN_LABEL = 'INCLURE EMPLACEMENT DROPZONE SUR ETIQUETTE';
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
