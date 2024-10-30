<?php

namespace App\Entity;

use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Interfaces\AttachmentContainer;
use App\Entity\Interfaces\StatusHistoryContainer;
use App\Entity\OperationHistory\ProductionHistoryRecord;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Traits\CleanedCommentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\ProductionRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductionRequestRepository::class)]
class ProductionRequest extends StatusHistoryContainer implements AttachmentContainer
{

    use AttachmentTrait;
    use FreeFieldsManagerTrait;
    use CleanedCommentTrait;

    public const NUMBER_PREFIX = 'P';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $manufacturingOrderNumber = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $emergency = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $expectedAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $projectNumber = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $productArticleCode = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $quantity = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $dropLocation = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $lineCount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToMany(mappedBy: 'productionRequest', targetEntity: StatusHistory::class, cascade: ['remove'])]
    private Collection $statusHistory;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $treatedAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'treatedProductionRequests')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $treatedBy = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'createdProductionRequests')]
    private ?Utilisateur $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: ProductionHistoryRecord::class, cascade: ['remove'])]
    private Collection $history;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Emplacement $destinationLocation = null;

    #[ORM\OneToOne(targetEntity: TrackingMovement::class, cascade: ['persist'])]
    private ?TrackingMovement $lastTracking = null;

    #[ORM\OneToMany(mappedBy: 'productionRequest', targetEntity: TrackingMovement::class)]
    private Collection $trackingMovements;

    public function __construct() {
        $this->statusHistory = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->history = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();

    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;

        return $this;
    }

    public function getManufacturingOrderNumber(): ?string {
        return $this->manufacturingOrderNumber;
    }

    public function setManufacturingOrderNumber(?string $manufacturingOrderNumber): self {
        $this->manufacturingOrderNumber = $manufacturingOrderNumber;

        return $this;
    }

    public function getEmergency(): ?string {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self {
        $this->emergency = $emergency;

        return $this;
    }

    public function getExpectedAt(): ?DateTime
    {
        return $this->expectedAt;
    }

    public function setExpectedAt(?DateTime $expectedAt): self {
        $this->expectedAt = $expectedAt;

        return $this;
    }

    public function getProjectNumber(): ?string {
        return $this->projectNumber;
    }

    public function setProjectNumber(?string $projectNumber): self {
        $this->projectNumber = $projectNumber;

        return $this;
    }

    public function getProductArticleCode(): ?string {
        return $this->productArticleCode;
    }

    public function setProductArticleCode(?string $productArticleCode): self {
        $this->productArticleCode = $productArticleCode;

        return $this;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self {
        $this->quantity = $quantity;

        return $this;
    }

    public function getDropLocation(): ?Emplacement {
        return $this->dropLocation;
    }

    public function setDropLocation(?Emplacement $dropLocation): self {
        $this->dropLocation = $dropLocation;

        return $this;
    }

    public function getLineCount(): ?int {
        return $this->lineCount;
    }

    public function setLineCount(?int $lineCount): self {
        $this->lineCount = $lineCount;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;
        $this->setCleanedComment($comment);

        return $this;
    }

    public function getStatusHistory(string $order = Criteria::ASC): Collection {
        return $this->statusHistory
            ->matching(Criteria::create()
                ->orderBy([
                    'date' => $order,
                    'id' => $order,
                ])
            );
    }

    public function addStatusHistory(StatusHistory $statusHistory): self  {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory[] = $statusHistory;
            $statusHistory->setProductionRequest($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self {
        if ($this->statusHistory->removeElement($statusHistory)) {
            if ($statusHistory->getProductionRequest() === $this) {
                $statusHistory->setProductionRequest(null);
            }
        }

        return $this;
    }

    public function clearStatusHistory(): self {
        $this->statusHistory = new ArrayCollection();
        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getTreatedAt(): ?DateTime
    {
        return $this->treatedAt;
    }

    public function setTreatedAt(DateTime $treatedAt): self {
        $this->treatedAt = $treatedAt;

        return $this;
    }

    public function getTreatedBy(): ?Utilisateur {
        return $this->treatedBy;
    }

    public function setTreatedBy(?Utilisateur $treatedBy): self {
        $this->treatedBy = $treatedBy;

        return $this;
    }

    public function getCreatedBy(): ?Utilisateur {
        return $this->createdBy;
    }

    public function setCreatedBy(?Utilisateur $createdBy): self {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, ProductionHistoryRecord>
     */
    public function getHistory(): Collection {
        return $this->history;
    }

    public function addHistory(ProductionHistoryRecord $history): self {
        if (!$this->history->contains($history)) {
            $this->history[] = $history;
            $history->setRequest($this);
        }

        return $this;
    }

    public function removeHistory(ProductionHistoryRecord $history): self {
        if ($this->history->removeElement($history)) {
            // set the owning side to null (unless already changed)
            if ($history->getRequest() === $this) {
                $history->setRequest(null);
            }
        }

        return $this;
    }

    public function serialize(): array {
        return [
            FixedFieldEnum::status->name => $this->getStatus(),
            FixedFieldEnum::comment->name => $this->getComment(),
            FixedFieldEnum::dropLocation->name => $this->getDropLocation(),
            FixedFieldEnum::manufacturingOrderNumber->name => $this->getManufacturingOrderNumber(),
            FixedFieldEnum::emergency->name => $this->getEmergency(),
            FixedFieldEnum::expectedAt->name => $this->getExpectedAt(),
            FixedFieldEnum::projectNumber->name => $this->getProjectNumber(),
            FixedFieldEnum::productArticleCode->name => $this->getProductArticleCode(),
            FixedFieldEnum::quantity->name => $this->getQuantity(),
            FixedFieldEnum::lineCount->name => $this->getLineCount(),
        ] + $this->getFreeFields();
    }

    public function getDestinationLocation(): ?Emplacement {
        return $this->destinationLocation;
    }

    public function setDestinationLocation(?Emplacement $destinationLocation): self {
        $this->destinationLocation = $destinationLocation;

        return $this;
    }

    public function getLastTracking(): ?TrackingMovement
    {
        return $this->lastTracking;
    }

    public function setLastTracking(?TrackingMovement $lastTracking): self
    {
        $this->lastTracking = $lastTracking;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if(!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setProductionRequest($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if($this->trackingMovements->removeElement($trackingMovement)) {
            if($trackingMovement->getProductionRequest() === $this) {
                $trackingMovement->setProductionRequest(null);
            }
        }

        return $this;
    }

    public function setTrackingMovements(?iterable $trackingMovements): self {
        foreach($this->getTrackingMovements()->toArray() as $trackingMovement) {
            $this->removeTrackingMovement($trackingMovement);
        }

        $this->trackingMovements = new ArrayCollection();
        foreach ($trackingMovements ?? [] as $trackingMovement) {
            $this->addTrackingMovement($trackingMovement);
        }

        return $this;
    }
}
