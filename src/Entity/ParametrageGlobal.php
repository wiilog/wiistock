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
    const DEFAULT_STATUT_LITIGE_ARR = 'DEFAULT_STATUT_LITIGE_ARR';
    const AUTO_PRINT_COLIS = 'AUTO PRINT COLIS';

    // mvt traca
    const CLOSE_AND_CLEAR_AFTER_NEW_MVT = 'CLOSE AND CLEAR AFTER NEW MVT';

    // réceptions
    const CREATE_DL_AFTER_RECEPTION = 'CREATION DL APRES RECEPTION';
    const CREATE_PREPA_AFTER_DL = 'CREATION PREPA APRES DL';
    const DEFAULT_LOCATION_RECEPTION = 'DEFAULT LOCATION RECEPTION';
    const DEFAULT_STATUT_LITIGE_REC = 'DEFAULT_STATUT_LITIGE_REC';

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

    // apparence
    const FONT_FAMILY = 'FONT FAMILY';
	const FONT_MONTSERRAT = 'Montserrat';
	const FONT_TAHOMA = 'Tahoma';
	const FONT_MYRIAD = 'Myriad';
	const DEFAULT_FONT_FAMILY = self::FONT_MONTSERRAT;

	// étiquettes
	const INCLUDE_BL_IN_LABEL = 'INCLURE BL SUR ETIQUETTE';
    const BARCODE_TYPE_IS_128 = 'barcode type';
    const QR_CODE = [
        "value" => false,
        "label" => 'QR Code'
    ];
    const CODE_128 = [
        "value" => true,
        "label" => 'Code 128'
    ];

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
     * @ORM\Column(type="string", length=255, nullable=true)
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
