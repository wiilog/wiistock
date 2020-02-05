<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParametrageGlobalRepository")
 */
class ParametrageGlobal
{
    const CREATE_DL_AFTER_RECEPTION = 'CREATION DL APRES RECEPTION';
    const REDIRECT_AFTER_NEW_ARRIVAL = 'REDIRECT AFTER NEW ARRIVAL';
    const CREATE_PREPA_AFTER_DL = 'CREATION PREPA APRES DL';
    const INCLUDE_BL_IN_LABEL = 'INCLURE BL SUR ETIQUETTE';
    const DEFAULT_LOCATION_RECEPTION = 'DEFAULT LOCATION RECEPTION';
    const FONT_FAMILY = 'FONT FAMILY';

    const BARCODE_TYPE_IS_128 = 'barcode type';
    const QR_CODE = [
        "value" => false,
        "label" => 'QR Code'
    ];
    const CODE_128 = [
        "value" => true,
        "label" => 'Code 128'
    ];

	const USES_UTF8 = 'utilise utf8';
    const ENCODAGE_UTF8 = [
        'value'=> true,
        'label'=> 'UTF-8'
    ];
    const ENCODAGE_EUW = [
        'value'=> false,
        'label'=> '1252 Europe de l\'ouest Windows'
    ];
	const DEFAULT_FONT_FAMILY = 'Montserrat';
	const FONT_MONTSERRAT = 'Montserrat';
	const FONT_TAHOMA = 'Tahoma';
	const FONT_MYRIAD = 'Myriad';

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
