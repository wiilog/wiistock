<?php

namespace App\Entity;

use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\TrackingMovementRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingMovementRepository::class)]
class TrackingMovement {

    use FreeFieldsManagerTrait;

    const TYPE_PRISE = 'prise';
    const TYPE_DEPOSE = 'depose';
    const TYPE_GROUP = 'groupage';
    const TYPE_PRISE_DEPOSE = 'prises et deposes';
    const TYPE_UNGROUP = 'dégroupage';
    const TYPE_EMPTY_ROUND = 'passage à vide';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'trackingMovements')]
    #[ORM\JoinColumn(name: 'pack_id', nullable: false)]
    private ?Pack $pack = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $uniqueIdForMobile = null;

    #[ORM\Column(type: 'datetime', length: 255, nullable: true)]
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $finished = null;

    #[ORM\Column(type: 'integer', nullable: false, options: ['default' => 1])]
    private ?int $quantity = null;

    #[ORM\ManyToOne(targetEntity: Reception::class, inversedBy: 'trackingMovements')]
    private ?Reception $reception = null;

    #[ORM\ManyToOne(targetEntity: Dispatch::class, inversedBy: 'trackingMovements')]
    private ?Dispatch $dispatch = null;

    #[ORM\OneToOne(mappedBy: 'lastDrop', targetEntity: Pack::class)]
    private ?Pack $linkedPackLastDrop = null;

    #[ORM\OneToOne(mappedBy: 'lastTracking', targetEntity: Pack::class)]
    private ?Pack $linkedPackLastTracking = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $groupIteration = null;

    #[ORM\OneToMany(mappedBy: 'firstDrop', targetEntity: LocationClusterRecord::class)]
    private Collection $firstDropRecords;

    #[ORM\OneToMany(mappedBy: 'lastTracking', targetEntity: LocationClusterRecord::class)]
    private Collection $lastTrackingRecords;

    #[ORM\ManyToOne(targetEntity: ReceptionReferenceArticle::class, inversedBy: 'trackingMovements')]
    private ?ReceptionReferenceArticle $receptionReferenceArticle = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'childTrackingMovements')]
    private ?Pack $packParent = null;

    #[ORM\ManyToMany(targetEntity: Attachment::class, mappedBy: 'trackingMovements')]
    private Collection $attachments;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'logisticUnitParentMovements')]
    private ?Pack $logisticUnitParent = null;

    public function __construct() {
        $this->quantity = 1;
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

    public function isTaking(): bool {
        return (
            $this->type
            &&  $this->type->getCode() === self::TYPE_PRISE
        );
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

    public function getReferenceArticle(): ?ReferenceArticle {
        return isset($this->pack)
            ? $this->pack->getReferenceArticle()
            : null;
    }

    public function getArticle(): ?Article {
        return isset($this->pack)
            ? $this->pack->getArticle()
            : null;
    }

    /**
     * @return Pack|null
     */
    public function getLinkedPackLastTracking(): ?Pack {
        return $this->linkedPackLastTracking;
    }

    public function setLinkedPackLastTracking(?Pack $linkedPackLastTracking): self {
        if($this->linkedPackLastTracking && $this->linkedPackLastTracking->getLastTracking() !== $this) {
            $oldLinkedPackLastTracking = $this->linkedPackLastTracking;
            $this->linkedPackLastTracking = null;
            $oldLinkedPackLastTracking->setLastTracking(null);
        }

        $this->linkedPackLastTracking = $linkedPackLastTracking;

        if($this->linkedPackLastTracking && $this->linkedPackLastTracking->getLastTracking() !== $this) {
            $this->linkedPackLastTracking->setLastTracking($this);
        }
        return $this;
    }

    public function getGroupIteration(): ?int {
        return $this->groupIteration;
    }

    public function setGroupIteration(int $groupIteration): self {
        $this->groupIteration = $groupIteration;

        return $this;
    }

    /**
     * @return Pack|null
     */
    public function getLinkedPackLastDrop(): ?Pack {
        return $this->linkedPackLastDrop;
    }

    public function setLinkedPackLastDrop(?Pack $linkedPackLastDrop): self {
        if($this->linkedPackLastDrop && $this->linkedPackLastDrop->getLastDrop() !== $this) {
            $oldLinkedPackLastDrop = $this->linkedPackLastDrop;
            $this->linkedPackLastDrop = null;
            $oldLinkedPackLastDrop->setLastDrop(null);
        }

        $this->linkedPackLastDrop = $linkedPackLastDrop;

        if($this->linkedPackLastDrop && $this->linkedPackLastDrop->getLastDrop() !== $this) {
            $this->linkedPackLastDrop->setLastDrop($this);
        }
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
        $this->pack = $pack;
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

    public function getPackParent(): ?Pack {
        return $this->packParent;
    }

    public function setPackParent(?Pack $packParent): self {
        if($this->packParent && $this->packParent !== $packParent) {
            $this->packParent->removeChildTrackingMovement($this);
        }

        $this->packParent = $packParent;

        if($packParent) {
            $packParent->addChildTrackingMovement($this);
        }

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
        if($this->logisticUnitParent && $this->logisticUnitParent !== $logisticUnitParent) {
            $this->logisticUnitParent->removeLogisticUnitParentMovement($this);
        }

        $this->logisticUnitParent = $logisticUnitParent;
        $logisticUnitParent?->addLogisticUnitParentMovement($this);

        return $this;
    }

}
