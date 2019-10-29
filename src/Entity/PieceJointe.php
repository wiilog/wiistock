<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PieceJointeRepository")
 */
class PieceJointe
{
	const MAIN_PATH = '/uploads/attachements/';
	const TEMP_PATH = self::MAIN_PATH . 'temp/';
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $originalName;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fileName;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Arrivage", inversedBy="piecesJointes")
     * @ORM\JoinColumn(nullable=true)
     */
    private $arrivage;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Litige", inversedBy="piecesJointes")
     * @ORM\JoinColumn(nullable=true)
     */
    private $litige;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

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

    public function getLitige(): ?Litige
    {
        return $this->litige;
    }

    public function setLitige(?Litige $litige): self
    {
        $this->litige = $litige;

        return $this;
    }
}
