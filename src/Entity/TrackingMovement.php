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
    const TYPE_DEPOSE = 'dépose';
    const TYPE_GROUP = 'groupage';
    const TYPE_PRISE_DEPOSE = 'prises et déposes';
    const TYPE_UNGROUP = 'dégroupage';
    const TYPE_EMPTY_ROUND = 'passage à vide';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    /**
     * @var Pack|null
     */
    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'trackingMovements')]
    #[ORM\JoinColumn(nullable: false, name: 'pack_id')]
    private $pack;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $uniqueIdForMobile;

    /**
     * @var DateTime
     */
    #[ORM\Column(type: 'datetime', length: 255, nullable: true)]
    private $datetime;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private $emplacement;

    /**
     * @var Statut|null
     */
    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private $type;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private $operateur;

    /**
     * @var MouvementStock|null
     */
    #[ORM\ManyToOne(targetEntity: MouvementStock::class)]
    #[ORM\JoinColumn(name: 'mouvement_stock_id', referencedColumnName: 'id', nullable: true)]
    private $mouvementStock;

    #[ORM\Column(type: 'text', nullable: true)]
    private $commentaire;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $finished;

    /**
     * @var int
     */
    #[ORM\Column(type: 'integer', nullable: false, options: ['default' => 1])]
    private $quantity;

    #[ORM\ManyToOne(targetEntity: Reception::class, inversedBy: 'trackingMovements')]
    private $reception;

    #[ORM\ManyToOne(targetEntity: Dispatch::class, inversedBy: 'trackingMovements')]
    private $dispatch;

    /**
     * @var Pack|null
     */
    #[ORM\OneToOne(targetEntity: Pack::class, mappedBy: 'lastDrop')]
    private $linkedPackLastDrop;

    /**
     * @var Pack|null
     */
    #[ORM\OneToOne(targetEntity: Pack::class, mappedBy: 'lastTracking')]
    private $linkedPackLastTracking;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $groupIteration;

    /**
     * @var ArrayCollection|null
     */
    #[ORM\OneToMany(targetEntity: LocationClusterRecord::class, mappedBy: 'firstDrop')]
    private $firstDropRecords;

    /**
     * @var ArrayCollection|null
     */
    #[ORM\OneToMany(targetEntity: LocationClusterRecord::class, mappedBy: 'lastTracking')]
    private $lastTrackingRecords;

    #[ORM\ManyToOne(targetEntity: ReceptionReferenceArticle::class, inversedBy: 'trackingMovements')]
    private $receptionReferenceArticle;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'childTrackingMovements')]
    private ?Pack $packParent = null;

    #[ORM\ManyToMany(targetEntity: Attachment::class, mappedBy: 'trackingMovements')]
    private Collection $attachments;

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

}
