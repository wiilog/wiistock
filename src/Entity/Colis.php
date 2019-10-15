<?php

namespace App\Entity;

use App\Entity\Utilities\BarcodeTrait;
use Doctrine\ORM\Mapping as ORM;
use App\Annotation\BarcodeAnnotation as IsBarcode;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ColisRepository")
 */
class Colis
{

    use BarcodeTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @IsBarcode(getter="getCode")
     */
    private $code;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Arrivage", inversedBy="colis")
     */
    private $arrivage;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(?string $code): self {
        $this->code = self::stripAccent($code);
        return $this;
    }

    public function getArrivage(): ?Arrivage
    {
        return $this->arrivage;
    }

    public function setArrivage(?Arrivage $arrivage): self
    {
        $this->arrivage = $arrivage;

        return $this;
    }
}
