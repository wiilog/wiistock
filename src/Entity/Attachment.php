<?php

namespace App\Entity;

use App\Entity\Transport\TransportOrder;
use App\Repository\AttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
class Attachment {

    const MAIN_PATH = '/uploads/attachements';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fullPath = null;

    #[ORM\ManyToOne(targetEntity: Arrivage::class, inversedBy: 'attachements')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Arrivage $arrivage = null;

    #[ORM\ManyToOne(targetEntity: Dispute::class, inversedBy: 'attachements')]
    #[ORM\JoinColumn(name: 'dispute_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    private ?Dispute $dispute = null;

    #[ORM\ManyToOne(targetEntity: TrackingMovement::class, inversedBy: 'attachements')]
    #[ORM\JoinColumn(name: 'mvt_traca_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    private ?TrackingMovement $trackingMovement = null;

    #[ORM\OneToOne(targetEntity: Import::class, mappedBy: 'csvFile')]
    private ?Import $importCsv = null;

    #[ORM\OneToOne(targetEntity: Import::class, mappedBy: 'logFile')]
    private ?Import $importLog = null;

    #[ORM\ManyToOne(targetEntity: Dispatch::class, inversedBy: 'attachements')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Dispatch $dispatch = null;

    #[ORM\ManyToOne(targetEntity: Handling::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Handling $handling = null;

    #[ORM\OneToOne(mappedBy: 'image', targetEntity: ReferenceArticle::class)]
    private ?ReferenceArticle $referenceArticle = null;

    #[ORM\OneToOne(mappedBy: 'signature', targetEntity: TransportOrder::class)]
    private ?TransportOrder $transportOrder = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getOriginalName(): ?string {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self {
        $this->originalName = $originalName;

        return $this;
    }

    public function getFileName(): ?string {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self {
        $this->fileName = $fileName;

        return $this;
    }

    public function getFullPath(): ?string {
        return $this->fullPath;
    }

    public function setFullPath(string $fullPath): self {
        $this->fullPath = $fullPath;

        return $this;
    }

    public function getArrivage(): ?Arrivage {
        return $this->arrivage;
    }

    public function setArrivage(?Arrivage $arrivage): self {
        $this->arrivage = $arrivage;

        return $this;
    }

    public function getDispatch(): ?Dispatch {
        return $this->dispatch;
    }

    public function setDispatch(?Dispatch $dispatch): self {
        $this->dispatch = $dispatch;

        return $this;
    }

    public function getHandling(): ?Handling {
        return $this->handling;
    }

    public function setHandling(?Handling $handling): self {
        $this->handling = $handling;

        return $this;
    }

    public function getDispute(): ?Dispute {
        return $this->dispute;
    }

    public function setDispute(?Dispute $dispute): self {
        $this->dispute = $dispute;

        return $this;
    }

    public function getTrackingMovement(): ?TrackingMovement {
        return $this->trackingMovement;
    }

    public function setTrackingMovement(?TrackingMovement $trackingMovement): self {
        $this->trackingMovement = $trackingMovement;

        return $this;
    }

    public function getImportLog(): ?Import {
        return $this->importLog;
    }

    public function setImportLog(?Import $importLog): self {
        $this->importLog = $importLog;

        return $this;
    }

    public function getImportCsv(): ?Import {
        return $this->importCsv;
    }

    public function setImportCsv(?Import $importCsv): self {
        $this->importCsv = $importCsv;

        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self {
        $this->referenceArticle = $referenceArticle;

        return $this;
    }

    public function getTransportOrder(): ?TransportOrder {
        return $this->transportOrder;
    }

    public function setTransportOrder(?TransportOrder $transportOrder): self {
        $this->transportOrder = $transportOrder;

        return $this;
    }

}
