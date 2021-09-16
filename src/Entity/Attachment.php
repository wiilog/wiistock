<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AttachmentRepository")
 */
class Attachment
{
    const MAIN_PATH = '/uploads/attachements';

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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $fullPath;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Arrivage", inversedBy="attachements")
     * @ORM\JoinColumn(nullable=true)
     */
    private $arrivage;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Litige", inversedBy="attachements")
     * @ORM\JoinColumn(name="litige_id", referencedColumnName="id", onDelete="CASCADE")
     * @ORM\JoinColumn(nullable=true)
     */
    private $litige;

    /**
     * @ORM\ManyToOne(targetEntity=TrackingMovement::class, inversedBy="attachements")
     * @ORM\JoinColumn(name="mvt_traca_id", referencedColumnName="id", onDelete="CASCADE")
     * @ORM\JoinColumn(nullable=true)
     */
    private $trackingMovement;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Import", mappedBy="csvFile")
     */
    private $importCsv;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Import", mappedBy="logFile")
     */
    private $importLog;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Dispatch", inversedBy="attachements")
     * @ORM\JoinColumn(nullable=true)
     */
    private $dispatch;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Handling", inversedBy="attachments")
     * @ORM\JoinColumn(nullable=true)
     */
    private $handling;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\ReferenceArticle", mappedBy="image")
     */
    private $referenceArticle;

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

    public function getFullPath(): ?string
    {
        return $this->fullPath;
    }

    public function setFullPath(string $fullPath): self
    {
        $this->fullPath = $fullPath;

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

    public function getDispatch(): ?Dispatch
    {
        return $this->dispatch;
    }

    public function setDispatch(?Dispatch $dispatch): self
    {
        $this->dispatch = $dispatch;

        return $this;
    }

    public function getHandling(): ?Handling
    {
        return $this->handling;
    }

    public function setHandling(?Handling $handling): self
    {
        $this->handling = $handling;

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

    public function getTrackingMovement(): ?TrackingMovement
    {
        return $this->trackingMovement;
    }

    public function setTrackingMovement(?TrackingMovement $trackingMovement): self
    {
        $this->trackingMovement = $trackingMovement;

        return $this;
    }

    public function getImportLog(): ?Import
    {
        return $this->importLog;
    }

    public function setImportLog(?Import $importLog): self
    {
        $this->importLog = $importLog;

        return $this;
    }

    public function getImportCsv(): ?Import
    {
        return $this->importCsv;
    }

    public function setImportCsv(?Import $importCsv): self
    {
        $this->importCsv = $importCsv;

        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle
    {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self
    {
        $this->referenceArticle = $referenceArticle;

        return $this;
    }
}
