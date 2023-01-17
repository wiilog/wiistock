<?php

namespace App\Entity;

use App\Entity\Interfaces\StatusHistoryContainer;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\DispatchRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: DispatchRepository::class)]
class Dispatch extends StatusHistoryContainer {

    use FreeFieldsManagerTrait;

    const CATEGORIE = 'acheminements';
    const NUMBER_PREFIX = 'A';
    /**
     * @var [string => bool] Associate field name to bool, if TRUE we saved it in user entity
     */
    const DELIVERY_NOTE_DATA = [
        'consignor' => true,
        'deliveryAddress' => false,
        'deliveryNumber' => false,
        'deliveryDate' => false,
        'dispatchEmergency' => false,
        'packs' => false,
        'salesOrderNumber' => false,
        'wayBill' => false,
        'customerPONumber' => false,
        'customerPODate' => false,
        'respOrderNb' => false,
        'projectNumber' => false,
        'username' => false,
        'userPhone' => false,
        'userFax' => false,
        'buyer' => false,
        'buyerPhone' => false,
        'buyerFax' => false,
        'invoiceNumber' => false,
        'soldNumber' => false,
        'invoiceTo' => false,
        'soldTo' => false,
        'endUserNo' => false,
        'deliverNo' => false,
        'endUser' => false,
        'deliverTo' => false,
        'consignor2' => true,
        'date' => false,
        'notes' => true,
    ];
    /**
     * @var [string => bool] Associate field name to bool, if TRUE we saved it in user entity
     */
    const WAYBILL_DATA = [
        'carrier' => false,
        'dispatchDate' => false,
        'consignor' => false,
        'receiver' => false,
        'consignorUsername' => false,
        'consignorEmail' => false,
        'receiverUsername' => false,
        'receiverEmail' => false,
        'locationFrom' => true,
        'locationTo' => true,
        'notes' => true,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $creationDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $carrierTrackingNumber = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $commandNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $emergency = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $endDate = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $projectNumber = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $businessUnit = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'treatedDispatches')]
    private ?Utilisateur $treatedBy = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'dispatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Statut $statut = null;

    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'dispatch')]
    private Collection $attachements;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'dispatchesFrom')]
    private ?Emplacement $locationFrom = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'dispatchesTo')]
    private ?Emplacement $locationTo = null;

    #[ORM\OneToMany(targetEntity: DispatchPack::class, mappedBy: 'dispatch', orphanRemoval: true)]
    private Collection $dispatchPacks;

    #[ORM\OneToMany(targetEntity: TrackingMovement::class, mappedBy: 'dispatch')]
    private Collection $trackingMovements;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'dispatches')]
    private ?Type $type = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $validationDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $treatmentDate = null;

    /**
     * @var array|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $waybillData;

    /**
     * @var array|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $deliveryNoteData;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'receivedDispatches')]
    #[ORM\JoinTable(name: 'dispatch_receiver')]
    private ?Collection $receivers;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'requestedDispatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $requester = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class, inversedBy: 'dispatches')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Transporteur $carrier = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $destination = null;

    #[ORM\OneToMany(mappedBy: 'dispatch', targetEntity: StatusHistory::class)]
    private Collection $statusHistory;

    public function __construct() {
        $this->dispatchPacks = new ArrayCollection();
        $this->attachements = new ArrayCollection();
        $this->waybillData = [];
        $this->deliveryNoteData = [];
        $this->receivers = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $date): self {
        $this->creationDate = $date;

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getReceivers(): ?Collection {
        return $this->receivers;
    }

    public function addReceiver(?Utilisateur $receiver): self {
        if(!$this->receivers->contains($receiver)) {
            $this->receivers[] = $receiver;
            if(!$receiver->getReceivedDispatches()->contains($this)) {
                $receiver->addReceivedDispatch($this);
            }
        }
        return $this;
    }

    public function removeReceiver(Utilisateur $receiver): self {
        if($this->receivers->removeElement($receiver)) {
            $receiver->removeReceivedDispatch($this);
        }
        return $this;
    }

    public function getRequester(): ?Utilisateur {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self {
        $this->requester = $requester;

        return $this;
    }

    public function getTreatedBy(): ?Utilisateur {
        return $this->treatedBy;
    }

    public function setTreatedBy(?Utilisateur $treatedBy): self {
        $this->treatedBy = $treatedBy;

        return $this;
    }

    public function getCarrier(): ?Transporteur {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self {
        $this->carrier = $carrier;
        return $this;
    }

    public function getCarrierTrackingNumber(): ?string {
        return $this->carrierTrackingNumber;
    }

    public function setCarrierTrackingNumber(?string $carrierTrackingNumber): self {
        $this->carrierTrackingNumber = $carrierTrackingNumber;
        return $this;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatus(?Statut $status): self {
        $this->statut = $status;

        return $this;
    }

    public function getCommandNumber(): ?string {
        return $this->commandNumber;
    }

    public function setCommandNumber(?string $commandNumber): self {
        $this->commandNumber = $commandNumber;

        return $this;
    }

    public function getCommentaire(): ?string {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection {
        return $this->attachements;
    }

    public function addAttachment(Attachment $attachment): self {
        if(!$this->attachements->contains($attachment)) {
            $this->attachements[] = $attachment;
            $attachment->setDispatch($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self {
        if($this->attachements->contains($attachment)) {
            $this->attachements->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if($attachment->getDispatch() === $this) {
                $attachment->setDispatch(null);
            }
        }

        return $this;
    }

    public function getLocationFrom(): ?Emplacement {
        return $this->locationFrom;
    }

    public function setLocationFrom(?Emplacement $locationFrom): self {
        $this->locationFrom = $locationFrom;

        return $this;
    }

    public function getLocationTo(): ?Emplacement {
        return $this->locationTo;
    }

    public function setLocationTo(?Emplacement $locationTo): self {
        $this->locationTo = $locationTo;

        return $this;
    }

    /**
     * @return Collection|DispatchPack[]
     */
    public function getDispatchPacks(): Collection {
        return $this->dispatchPacks;
    }

    public function addDispatchPack(DispatchPack $dispatchPack): self {
        if(!$this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks[] = $dispatchPack;
            $dispatchPack->setDispatch($this);
        }

        return $this;
    }

    public function removeDispatchPack(DispatchPack $dispatchPack): self {
        if($this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks->removeElement($dispatchPack);
            // set the owning side to null (unless already changed)
            if($dispatchPack->getDispatch() === $this) {
                $dispatchPack->setDispatch(null);
            }
        }

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getEmergency(): ?string {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self {
        $this->emergency = $emergency;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getStartDate(): ?DateTime {
        return $this->startDate;
    }

    /**
     * @param DateTime|null $startDate
     * @return self
     */
    public function setStartDate(?DateTime $startDate): self {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getEndDate(): ?DateTime {
        return $this->endDate;
    }

    /**
     * @param DateTime|null $endDate
     * @return self
     */
    public function setEndDate(?DateTime $endDate): self {
        $this->endDate = $endDate;
        return $this;
    }

    public function getValidationDate(): ?DateTime {
        return $this->validationDate;
    }

    public function setValidationDate(?DateTime $validationDate): self {
        $this->validationDate = $validationDate;
        return $this;
    }

    public function getTreatmentDate(): ?DateTime {
        return $this->treatmentDate;
    }

    public function setTreatmentDate(?DateTime $treatmentDate): self {
        $this->treatmentDate = $treatmentDate;
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
            $trackingMovement->setDispatch($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if($trackingMovement->getDispatch() === $this) {
                $trackingMovement->setDispatch(null);
            }
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getProjectNumber(): ?string {
        return $this->projectNumber;
    }

    /**
     * @param string|null $projectNumber
     * @return self
     */
    public function setProjectNumber(?string $projectNumber): self {
        $this->projectNumber = $projectNumber;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBusinessUnit(): ?string {
        return $this->businessUnit;
    }

    /**
     * @param string|null $businessUnit
     * @return self
     */
    public function setBusinessUnit(?string $businessUnit): self {
        $this->businessUnit = $businessUnit;
        return $this;
    }

    /**
     * @return array
     */
    public function getWaybillData(): array {
        return $this->waybillData ?? [];
    }

    /**
     * @param array $waybillData
     * @return self
     */
    public function setWaybillData(array $waybillData): self {
        $this->waybillData = $waybillData;
        return $this;
    }

    /**
     * @return array
     */
    public function getDeliveryNoteData(): array {
        return $this->deliveryNoteData ?? [];
    }

    /**
     * @param array $deliveryNoteData
     * @return self
     */
    public function setDeliveryNoteData(array $deliveryNoteData): self {
        $this->deliveryNoteData = $deliveryNoteData;
        return $this;
    }

    public function getDestination(): ?string {
        return $this->destination;
    }

    public function setDestination(?string $destination): self {
        $this->destination = $destination;

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

    public function addStatusHistory(StatusHistory $statusHistory): self
    {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory[] = $statusHistory;
            $statusHistory->setDispatch($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self
    {
        if ($this->statusHistory->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getHandling() === $this) {
                $statusHistory->setHandling(null);
            }
        }

        return $this;
    }

    public function hasReferenceArticle() {
        return Stream::from($this->dispatchPacks)
            ->some(fn(DispatchPack $dispatchPack) => count($dispatchPack->getReferenceArticles()) > 0
        );
    }
}
