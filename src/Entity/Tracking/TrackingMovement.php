<?php

namespace App\Entity\Tracking;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\Attachment;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\Emplacement;
use App\Entity\Interfaces\AttachmentContainer;
use App\Entity\Livraison;
use App\Entity\LocationClusterRecord;
use App\Entity\MouvementStock;
use App\Entity\Nature;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ProductionRequest;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Statut;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Entity\Utilisateur;
use App\Repository\Tracking\TrackingMovementRepository;
use App\Service\Tracking\TrackingDelayService;
use App\Service\Tracking\TrackingMovementService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingMovementRepository::class)]
#[ORM\Index(fields: ["datetime"])]
#[ORM\Index(fields: ["uniqueIdForMobile"])]
class TrackingMovement implements AttachmentContainer {

    use FreeFieldsManagerTrait;
    use AttachmentTrait;

    const DEFAULT_QUANTITY = 1;

    const TYPE_PRISE = 'prise';
    const TYPE_DEPOSE = 'depose';
    const TYPE_GROUP = 'groupage';
    const TYPE_PRISE_DEPOSE = 'prises et deposes';
    const TYPE_UNGROUP = 'dégroupage';
    const TYPE_EMPTY_ROUND = 'passage à vide';
    const TYPE_DROP_LU = 'dépose dans UL';
    const TYPE_PICK_LU = 'prise dans UL';
    const TYPE_PACK_SPLIT = 'division';
    const TYPE_INIT_TRACKING_DELAY = 'init délai traça';
    const DEFAULT_TYPE = self::TYPE_PRISE_DEPOSE;

    const DISPATCH_ENTITY = 'dispatch';
    const ARRIVAL_ENTITY = 'arrival';
    const RECEPTION_ENTITY = 'reception';
    const TRANSFER_ORDER_ENTITY = 'transferOrder';
    const PREPARATION_ENTITY = 'preparation';
    const DELIVERY_ORDER_ENTITY = 'deliveryOrder';
    const DELIVERY_REQUEST_ENTITY = 'deliveryRequest';
    const SHIPPING_REQUEST_ENTITY = 'shippingRequest';
    const PRODUCTION_REQUEST_ENTITY = 'productionRequest';

    /**
     * @var array{
     *      previousTrackingEvent?: TrackingEvent,
     *      nextTrackingEvent?: TrackingEvent,
     *      nextType?: string,
     *  }
     * Data build by tracking movement creation function createTrackingMovement
     * The data will be used in TrackingMovementListener after flush to launch message to recalculate delay.
     *
     * @see TrackingMovementService::createTrackingMovement()
     * @see TrackingDelayService::shouldCalculateTrackingDelay()
     */
    public array $calculateTrackingDelayData = [];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'trackingMovements')]
    #[ORM\JoinColumn(name: 'pack_id', nullable: false)]
    private ?Pack $pack = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $uniqueIdForMobile = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, length: 255, nullable: true)]
    private ?DateTime $datetime = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $emplacement = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $type = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $operateur = null;

    #[ORM\ManyToOne(targetEntity: MouvementStock::class)]
    #[ORM\JoinColumn(name: 'mouvement_stock_id', referencedColumnName: 'id', nullable: true)]
    private ?MouvementStock $mouvementStock = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $finished = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => self::DEFAULT_QUANTITY])]
    private ?int $quantity = self::DEFAULT_QUANTITY;

    #[ORM\Column(type: Types::BIGINT, nullable: true, options: ["unsigned" => true])]
    private ?int $orderIndex = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, enumType: TrackingEvent::class)]
    private ?TrackingEvent $event = null;

    #[ORM\ManyToOne(targetEntity: Reception::class, inversedBy: 'trackingMovements')]
    private ?Reception $reception = null;

    #[ORM\ManyToOne(targetEntity: Dispatch::class, inversedBy: 'trackingMovements')]
    private ?Dispatch $dispatch = null;

    #[ORM\ManyToOne(targetEntity: Preparation::class, inversedBy: 'trackingMovements')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Preparation $preparation = null;

    #[ORM\ManyToOne(targetEntity: Livraison::class, inversedBy: 'trackingMovements')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Livraison $delivery = null;

    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'trackingMovements')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Demande $deliveryRequest = null;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'trackingMovements')]
    private ?ShippingRequest $shippingRequest = null;

    #[ORM\ManyToOne(targetEntity: ProductionRequest::class, inversedBy: 'trackingMovements')]
    private ?ProductionRequest $productionRequest = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $groupIteration = null;

    #[ORM\OneToMany(mappedBy: 'firstDrop', targetEntity: LocationClusterRecord::class, cascade: ["persist"])]
    private Collection $firstDropRecords;

    #[ORM\OneToMany(mappedBy: 'lastTracking', targetEntity: LocationClusterRecord::class, cascade: ["persist"])]
    private Collection $lastTrackingRecords;

    #[ORM\ManyToOne(targetEntity: ReceptionReferenceArticle::class, inversedBy: 'trackingMovements')]
    private ?ReceptionReferenceArticle $receptionReferenceArticle = null;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    private ?Pack $packGroup = null;

    #[ORM\ManyToMany(targetEntity: Attachment::class, mappedBy: 'trackingMovements')]
    private Collection $attachments;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    private ?Pack $logisticUnitParent = null;

    #[ORM\ManyToOne(targetEntity: TrackingMovement::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?TrackingMovement $mainMovement = null;

    /**
     * The column is filled only if the movement has triggered the nature changement
     * It contains the nature before the nature changement
     */
    #[ORM\ManyToOne(targetEntity: Nature::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Nature $oldNature = null;

    /**
     * The column is filled only if the movement has triggered the nature changement
     * It contains the nature after the nature changement
     */
    #[ORM\ManyToOne(targetEntity: Nature::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Nature $newNature = null;

    public function __construct() {
        $this->firstDropRecords = new ArrayCollection();
        $this->lastTrackingRecords = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getUniqueIdForMobile(): ?string {
        return $this->uniqueIdForMobile;
    }

    public function setUniqueIdForMobile(?string $uniqueIdForMobile): self {
        $this->uniqueIdForMobile = $uniqueIdForMobile;

        return $this;
    }

    public function getCommentaire(): ?string {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getEmplacement(): ?Emplacement {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): self {
        $this->emplacement = $emplacement;

        return $this;
    }

    public function getOperateur(): ?Utilisateur {
        return $this->operateur;
    }

    public function setOperateur(?Utilisateur $operateur): self {
        $this->operateur = $operateur;

        return $this;
    }

    public function getType(): ?Statut {
        return $this->type;
    }

    public function isDrop(): bool {
        return (
            $this->type
            && $this->type->getCode() === self::TYPE_DEPOSE
        );
    }

    public function isPicking(): bool {
        return (
            $this->type
            &&  $this->type->getCode() === self::TYPE_PRISE
        );
    }

    public function isInitTrackingDelay(): bool {
        return (
            $this->type
            &&  $this->type->getCode() === self::TYPE_INIT_TRACKING_DELAY
        );
    }

    public function isStart(): bool {
        return $this->event === TrackingEvent::START;
    }

    public function isPause(): bool {
        return $this->event === TrackingEvent::PAUSE;
    }

    public function isStop(): bool {
        return $this->event === TrackingEvent::STOP;
    }

    public function setType(?Statut $type): self {
        $this->type = $type;

        return $this;
    }

    public function getDatetime(): ?DateTime {
        return $this->datetime;
    }

    public function setDatetime(?DateTime $datetime): self {
        $this->datetime = $datetime;

        return $this;
    }

    public function isFinished(): ?bool {
        return $this->finished;
    }

    public function setFinished(?bool $finished): self {
        $this->finished = $finished;
        return $this;
    }

    public function getMouvementStock(): ?MouvementStock {
        return $this->mouvementStock;
    }

    public function setMouvementStock(?MouvementStock $mouvementStock): self {
        $this->mouvementStock = $mouvementStock;
        return $this;
    }

    public function getFinished(): ?bool {
        return $this->finished;
    }

    public function getReception(): ?Reception {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self {
        $this->reception = $reception;

        return $this;
    }

    public function getArrivage(): ?Arrivage {
        return isset($this->pack)
            ? $this->pack->getArrivage()
            : null;
    }

    public function setArrivage(?Arrivage $arrivage): self {
        $this->arrivage = $arrivage;

        return $this;
    }

    public function getDispatch() {
        return $this->dispatch;
    }

    /**
     * @param mixed $dispatch
     * @return TrackingMovement
     */
    public function setDispatch($dispatch): self {
        $this->dispatch = $dispatch;
        return $this;
    }

    public function getPreparation(): ?Preparation {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self {
        $this->preparation = $preparation;

        return $this;
    }

    public function getDelivery(): ?Livraison {
        return $this->delivery;
    }

    public function setDelivery(?Livraison $delivery): self {
        $this->delivery = $delivery;

        return $this;
    }

    public function setDeliveryRequest(?Demande $deliveryRequest): self {
        $this->deliveryRequest = $deliveryRequest;

        return $this;
    }

    public function getDeliveryRequest(): ?Demande {
        return $this->deliveryRequest;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        return isset($this->pack)
            ? $this->pack->getReferenceArticle()
            : null;
    }

    public function getPackArticle(): ?Article {
        return isset($this->pack)
            ? $this->pack->getArticle()
            : null;
    }

    public function getGroupIteration(): ?int {
        return $this->groupIteration;
    }

    public function setGroupIteration(int $groupIteration): self {
        $this->groupIteration = $groupIteration;

        return $this;
    }

    public function getReceptionReferenceArticle(): ?ReceptionReferenceArticle {
        return $this->receptionReferenceArticle;
    }

    public function setReceptionReferenceArticle(?ReceptionReferenceArticle $receptionReferenceArticle): self {
        $this->receptionReferenceArticle = $receptionReferenceArticle;

        return $this;
    }

    public function setPack(?Pack $pack): self {
        if($this->pack && $this->pack !== $pack) {
            $this->pack->removeTrackingMovement($this);
        }
        $this->pack = $pack;
        $pack?->addTrackingMovement($this);
        return $this;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    /**
     * @return int
     */
    public function getQuantity(): int {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     * @return self
     */
    public function setQuantity(int $quantity): self {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getFirstDropsRecords(): ?Collection {
        return $this->firstDropRecords;
    }

    /**
     * @param LocationClusterRecord $recored
     * @return $this
     */
    public function addFirstDropRecord(LocationClusterRecord $recored): self {
        if(!$this->firstDropRecords->contains($recored)) {
            $this->firstDropRecords[] = $recored;
        }

        return $this;
    }

    /**
     * @param LocationClusterRecord $record
     * @return $this
     */
    public function removeFirstDropRecord(LocationClusterRecord $record): self {
        if($this->firstDropRecords->contains($record)) {
            $this->firstDropRecords->removeElement($record);
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getLastTrackingRecords(): ?Collection {
        return $this->lastTrackingRecords;
    }

    /**
     * @param LocationClusterRecord $record
     * @return $this
     */
    public function addLastTrackingRecord(LocationClusterRecord $record): self {
        if(!$this->lastTrackingRecords->contains($record)) {
            $this->lastTrackingRecords[] = $record;
        }

        return $this;
    }

    /**
     * @param LocationClusterRecord $record
     * @return $this
     */
    public function removeLastTrackingRecord(LocationClusterRecord $record): self {
        if($this->lastTrackingRecords->contains($record)) {
            $this->lastTrackingRecords->removeElement($record);
        }

        return $this;
    }

    public function getPackGroup(): ?Pack {
        return $this->packGroup;
    }

    public function setPackGroup(?Pack $packGroup): self {
        $this->packGroup = $packGroup;

        return $this;
    }

    /**
     * @return Collection<int, Attachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments[] = $attachment;
            $attachment->addTrackingMovement($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        if ($this->attachments->removeElement($attachment)) {
            $attachment->removeTrackingMovement($this);
        }

        return $this;
    }

    public function getLogisticUnitParent(): ?Pack {
        return $this->logisticUnitParent;
    }

    public function setLogisticUnitParent(?Pack $logisticUnitParent): self {
        $this->logisticUnitParent = $logisticUnitParent;
        return $this;
    }

    public function getMainMovement(): ?TrackingMovement {
        return $this->mainMovement;
    }

    public function setMainMovement(?TrackingMovement $movement): self {
        $this->mainMovement = $movement;
        return $this;
    }

    public function setShippingRequest(?ShippingRequest $shippingRequest): self {
        $this->shippingRequest = $shippingRequest;

        return $this;
    }

    public function getShippingRequest(): ?ShippingRequest {
        return $this->shippingRequest;
    }

    public function setProductionRequest(?ProductionRequest $productionRequest): self {
        $this->productionRequest = $productionRequest;
        return $this;
    }

    public function getProductionRequest(): ?ProductionRequest {
        return $this->productionRequest;
    }

    public function setOrderIndex(?int $orderIndex): self {
        $this->orderIndex = $orderIndex;
        return $this;
    }

    public function getOrderIndex(): ?int {
        return $this->orderIndex;
    }

    public function getEvent(): ?TrackingEvent {
        return $this->event;
    }

    public function setEvent(?TrackingEvent $event): self {
        $this->event = $event;
        return $this;
    }

    public function getOldNature(): ?Nature
    {
        return $this->oldNature;
    }

    public function setOldNature(?Nature $oldNature): self
    {
        $this->oldNature = $oldNature;

        return $this;
    }

    public function getNewNature(): ?Nature {
        return $this->newNature;
    }

    public function setNewNature(?Nature $newNature): self {
        $this->newNature = $newNature;

        return $this;
    }
}
