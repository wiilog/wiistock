<?php

namespace App\Entity;

use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorMessageTrait;
use App\Entity\ShippingRequest\ShippingRequestPack;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportHistory;
use App\Helper\FormatHelper;
use App\Repository\PackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PackRepository::class)]
class Pack implements PairedEntity {

    use SensorMessageTrait;

    public const CONFIRM_CREATE_GROUP = 'CONFIRM_CREATE_GROUP';
    public const IN_ONGOING_RECEPTION = 'IN_ONGOING_RECEPTION';
    public const PACK_IS_GROUP = 'PACK_IS_GROUP';
    public const EMPTY_ROUND_PACK = 'passageavide';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $code = null;

    #[ORM\ManyToOne(targetEntity: Arrivage::class, inversedBy: 'packs')]
    private ?Arrivage $arrivage = null;

    #[ORM\ManyToMany(targetEntity: Dispute::class, mappedBy: 'packs')]
    private Collection $disputes;

    #[ORM\ManyToOne(targetEntity: Nature::class, inversedBy: 'packs')]
    private ?Nature $nature = null;

    #[ORM\OneToOne(inversedBy: 'linkedPackLastDrop', targetEntity: TrackingMovement::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?TrackingMovement $lastDrop = null;

    #[ORM\OneToOne(inversedBy: 'linkedPackLastTracking', targetEntity: TrackingMovement::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?TrackingMovement $lastTracking = null;

    #[ORM\OneToMany(mappedBy: 'pack', targetEntity: TrackingMovement::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderBy(['datetime' => 'DESC', 'id' => 'DESC'])]
    private Collection $trackingMovements;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private ?int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, nullable: true)]
    private ?float $weight = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, nullable: true)]
    private ?float $volume = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $groupIteration = null;

    #[ORM\OneToMany(mappedBy: 'pack', targetEntity: DispatchPack::class, orphanRemoval: true)]
    private Collection $dispatchPacks;

    #[ORM\OneToMany(mappedBy: 'pack', targetEntity: LocationClusterRecord::class, cascade: ['remove'])]
    private Collection $locationClusterRecords;

    #[ORM\OneToOne(inversedBy: 'trackingPack', targetEntity: Article::class, cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\OneToOne(inversedBy: 'trackingPack', targetEntity: ReferenceArticle::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ReferenceArticle $referenceArticle = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Pack $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Pack::class)]
    private ?Collection $children;

    #[ORM\OneToMany(mappedBy: 'packParent', targetEntity: TrackingMovement::class)]
    private ?Collection $childTrackingMovements;

    #[ORM\OneToMany(mappedBy: 'pack', targetEntity: Pairing::class, cascade: ['remove'])]
    private Collection $pairings;

    #[ORM\Column(type: 'boolean')]
    private bool $deliveryDone = false;

    #[ORM\OneToMany(mappedBy: 'pack', targetEntity: TransportHistory::class)]
    private Collection $transportHistory;

    #[ORM\OneToOne(mappedBy: 'pack', targetEntity: TransportDeliveryOrderPack::class)]
    private ?TransportDeliveryOrderPack $transportDeliveryOrderPack = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    private ?Project $project = null;

    #[ORM\OneToMany(mappedBy: "currentLogisticUnit", targetEntity: Article::class)]
    private Collection $childArticles;

    #[ORM\OneToMany(mappedBy: 'pack', targetEntity: ProjectHistoryRecord::class, cascade: ["persist", "remove"])]
    private Collection $projectHistoryRecords;

    #[ORM\Column(type: 'boolean')]
    private ?bool $articleContainer = false;

    #[ORM\OneToOne(mappedBy: 'pack', targetEntity: ShippingRequestPack::class)]
    private ?ShippingRequestPack $shippingRequestPack = null;

    public function __construct() {
        $this->disputes = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
        $this->dispatchPacks = new ArrayCollection();
        $this->locationClusterRecords = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->childTrackingMovements = new ArrayCollection();
        $this->pairings = new ArrayCollection();
        $this->sensorMessages = new ArrayCollection();
        $this->childArticles = new ArrayCollection();
        $this->projectHistoryRecords = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getCode(): ?string {
        return trim($this->code);
    }

    public function setCode(?string $code): self {
        $this->code = trim($code);
        return $this;
    }

    public function getArrivage(): ?Arrivage {
        return $this->arrivage;
    }

    public function setArrivage(?Arrivage $arrivage): self {
        $this->arrivage = $arrivage;

        return $this;
    }

    /**
     * @return Collection|Dispute[]
     */
    public function getDisputes(): Collection {
        return $this->disputes;
    }

    public function addDispute(Dispute $dispute): self {
        if(!$this->disputes->contains($dispute)) {
            $this->disputes[] = $dispute;
            $dispute->addPack($this);
        }

        return $this;
    }

    public function removeDispute(Dispute $dispute): self {
        if($this->disputes->contains($dispute)) {
            $this->disputes->removeElement($dispute);
            $dispute->removePack($this);
        }

        return $this;
    }

    public function getNature(): ?Nature {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self {
        $this->nature = $nature;

        return $this;
    }

    public function getLastDrop(): ?TrackingMovement {
        return $this->lastDrop;
    }

    public function setLastDrop(?TrackingMovement $lastDrop): self {
        if($this->lastDrop && $this->lastDrop->getLinkedPackLastDrop() !== $this) {
            $oldLastDrop = $this->lastDrop;
            $this->lastDrop = null;
            $oldLastDrop->setLinkedPackLastDrop(null);
        }

        $this->lastDrop = $lastDrop;

        if($this->lastDrop && $this->lastDrop->getLinkedPackLastDrop() !== $this) {
            $this->lastDrop->setLinkedPackLastDrop($this);
        }

        return $this;
    }

    public function getLastTracking(): ?TrackingMovement {
        return $this->lastTracking;
    }

    public function setLastTracking(?TrackingMovement $lastTracking): self {
        if($this->lastTracking && $this->lastTracking->getLinkedPackLastTracking() !== $this) {
            $oldLastTracking = $this->lastTracking;
            $this->lastTracking = null;
            $oldLastTracking->setLinkedPackLastTracking(null);
        }

        $this->lastTracking = $lastTracking;

        if($this->lastTracking && $this->lastTracking->getLinkedPackLastTracking() !== $this) {
            $this->lastTracking->setLinkedPackLastTracking($this);
        }

        return $this;
    }

    /**
     * @param string $order
     * @return Collection|TrackingMovement[]
     */
    public function getTrackingMovements(string $order = 'DESC'): Collection {
        $criteria = Criteria::create()
            ->orderBy([
                'datetime' => $order,
                'id' => $order,
            ]);
        return $this->trackingMovements->matching($criteria);
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if(!$this->trackingMovements->contains($trackingMovement)) {
            // push on top new movement
            $trackingMovements = $this->trackingMovements->toArray();
            array_unshift($trackingMovements, $trackingMovement);
            $this->trackingMovements = new ArrayCollection($trackingMovements);

            $trackingMovement->setPack($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if($trackingMovement->getPack() === $this) {
                $trackingMovement->setPack(null);
            }
        }

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
            $dispatchPack->setPack($this);
        }

        return $this;
    }

    public function removeDispatchPack(DispatchPack $dispatchPack): self {
        if($this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks->removeElement($dispatchPack);
            // set the owning side to null (unless already changed)
            if($dispatchPack->getPack() === $this) {
                $dispatchPack->setPack(null);
            }
        }

        return $this;
    }

    public function getQuantity(): int {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self {
        $this->quantity = $quantity;
        return $this;
    }

    public function getWeight(): ?string {
        return $this->weight;
    }

    public function setWeight(?string $weight): self {
        $this->weight = $weight;
        return $this;
    }

    public function getVolume(): ?string {
        return $this->volume;
    }

    public function setVolume(?string $volume): self {
        $this->volume = $volume;
        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return LocationClusterRecord[]|Collection
     */
    public function getLocationClusterRecords(): Collection {
        return $this->locationClusterRecords;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return self
     */
    public function addLocationClusterRecord(LocationClusterRecord $locationClusterRecord): self {
        if(!$this->locationClusterRecords->contains($locationClusterRecord)) {
            $this->locationClusterRecords[] = $locationClusterRecord;
            $locationClusterRecord->setPack($this);
        }
        return $this;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return self
     */
    public function removeLocationClusterRecord(LocationClusterRecord $locationClusterRecord): self {
        if($this->locationClusterRecords->contains($locationClusterRecord)) {
            $this->locationClusterRecords->removeElement($locationClusterRecord);
            // set the owning side to null (unless already changed)
            if($locationClusterRecord->getPack() === $this) {
                $locationClusterRecord->setPack(null);
            }
        }
        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        if($this->article && $this->article->getTrackingPack() !== $this) {
            $oldArticle = $this->article;
            $this->article = null;
            $oldArticle->setTrackingPack(null);
        }
        $this->article = $article;
        if($this->article && $this->article->getTrackingPack() !== $this) {
            $this->article->setTrackingPack($this);
        }
        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self {
        if(isset($this->referenceArticle)
            && $this->referenceArticle !== $referenceArticle) {
            $this->referenceArticle->setTrackingPack(null);
        }
        $this->referenceArticle = $referenceArticle;
        if(isset($this->referenceArticle)
            && $this->referenceArticle->getTrackingPack() !== $referenceArticle->getTrackingPack()) {
            $this->referenceArticle->setTrackingPack($this);
        }
        return $this;
    }

    public function isGroup(): ?int {
        return $this->getGroupIteration() !== null;
    }

    public function incrementGroupIteration(): self {
        $iteration = $this->getGroupIteration() ?? 0;
        $this->setGroupIteration($iteration + 1);
        return $this;
    }

    public function getGroupIteration(): ?int {
        return $this->groupIteration;
    }

    public function setGroupIteration(int $groupIteration): self {
        $this->groupIteration = $groupIteration;

        return $this;
    }

    public function getParent(): ?Pack {
        return $this->parent;
    }

    public function setParent(?Pack $parent): self {
        if($this->parent
            && $this->parent !== $parent) {
            $this->parent->removeChild($this);
        }
        $this->parent = $parent;
        if($parent) {
            $parent->addChild($this);
        }

        return $this;
    }

    /**
     * @return Collection|Pack[]
     */
    public function getChildren(): Collection {
        return $this->children;
    }

    public function addChild(Pack $child): self {
        if(!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(Pack $child): self {
        if($this->children->removeElement($child)) {
            if($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function setChildren(?array $children): self {
        foreach($this->getChildren()->toArray() as $child) {
            $this->removeChild($child);
        }

        $this->children = new ArrayCollection();

        foreach($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    /**
     * @return Collection|TrackingMovement[]
     */
    public function getChildTrackingMovements(): Collection {
        return $this->childTrackingMovements;
    }

    public function addChildTrackingMovement(TrackingMovement $movement): self {
        if(!$this->childTrackingMovements->contains($movement)) {
            $this->childTrackingMovements[] = $movement;
            $movement->setPackParent($this);
        }

        return $this;
    }

    public function removeChildTrackingMovement(TrackingMovement $movement): self {
        if($this->childTrackingMovements->removeElement($movement)) {
            if($movement->getPackParent() === $this) {
                $movement->setPackParent(null);
            }
        }

        return $this;
    }

    public function setChildTrackingMovements(?array $movements): self {
        foreach($this->getChildTrackingMovements()->toArray() as $movement) {
            $this->removeChildTrackingMovement($movement);
        }

        $this->childTrackingMovements = new ArrayCollection();

        foreach($movements as $movement) {
            $this->addChildTrackingMovement($movement);
        }

        return $this;
    }

    public function serialize(): array {
        return $this->isGroup()
            ? $this->serializeGroup()
            : $this->serializePack();
    }

    private function serializePack(): array {
        $lastTracking = $this->getLastTracking();
        return [
            "code" => $this->getCode(),
            "ref_article" => $this->getCode(),
            "nature_id" => $this->getNature() ? $this->getNature()->getId() : null,
            "quantity" => $lastTracking ? $lastTracking->getQuantity() : 1,
            "type" => $lastTracking && $lastTracking->getType() ? $lastTracking->getType()->getCode() : null,
            "ref_emplacement" => $lastTracking && $lastTracking->getEmplacement() ? $lastTracking->getEmplacement()->getLabel() : null,
            "date" => $lastTracking ? FormatHelper::datetime($lastTracking->getDatetime()) : '',
        ];
    }

    private function serializeGroup(): array {
        return [
            "id" => $this->getId(),
            "code" => $this->getCode(),
            "natureId" => $this->getNature() ? $this->getNature()->getId() : null,
            "packs" => $this->getChildren()
                ->map(fn(Pack $pack) => $pack->serialize())
                ->toArray(),
        ];
    }

    /**
     * @return Collection|Pairing[]
     */
    public function getPairings(): Collection {
        return $this->pairings;
    }

    public function getActivePairing(): ?Pairing {
        $criteria = Criteria::create();
        return $this->pairings
            ->matching(
                $criteria
                    ->andWhere(Criteria::expr()->eq('active', true))
                    ->setMaxResults(1)
            )
            ->first() ?: null;
    }

    public function addPairing(Pairing $pairing): self {
        if(!$this->pairings->contains($pairing)) {
            $this->pairings[] = $pairing;
            $pairing->setPack($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self {
        if($this->pairings->removeElement($pairing)) {
            // set the owning side to null (unless already changed)
            if($pairing->getPack() === $this) {
                $pairing->setPack(null);
            }
        }

        return $this;
    }

    public function __toString() {
        return $this->getCode();
    }

    public function isDeliveryDone(): bool {
        return $this->deliveryDone;
    }

    public function setIsDeliveryDone(bool $deliveryDone): self {
        $this->deliveryDone = $deliveryDone;
        return $this;
    }

    public function getTransportDeliveryOrderPack(): ?TransportDeliveryOrderPack {
        return $this->transportDeliveryOrderPack;
    }

    public function setTransportDeliveryOrderPack(?TransportDeliveryOrderPack $transportDeliveryOrderPack): self {
        if($this->transportDeliveryOrderPack && $this->transportDeliveryOrderPack->getPack() !== $this) {
            $oldTransportDeliveryOrderPack = $this->transportDeliveryOrderPack;
            $this->transportDeliveryOrderPack = null;
            $oldTransportDeliveryOrderPack->setPack(null);
        }
        $this->transportDeliveryOrderPack = $transportDeliveryOrderPack;
        if($this->transportDeliveryOrderPack && $this->transportDeliveryOrderPack->getPack() !== $this) {
            $this->transportDeliveryOrderPack->setPack($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportHistory>
     */
    public function getTransportHistories(): Collection {
        return $this->transportHistory;
    }

    public function addTransportHistory(TransportHistory $transportHistory): self
    {
        if (!$this->transportHistory->contains($transportHistory)) {
            $this->transportHistory[] = $transportHistory;
            $transportHistory->setPack($this);
        }

        return $this;
    }

    public function removeTransportHistory(TransportHistory $transportHistory): self
    {
        if ($this->transportHistory->removeElement($transportHistory)) {
            // set the owning side to null (unless already changed)
            if ($transportHistory->getPack() === $this) {
                $transportHistory->setPack(null);
            }
        }

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getChildArticles(): Collection {
        return $this->childArticles;
    }

    public function addChildArticle(Article $childArticle): self {
        if (!$this->childArticles->contains($childArticle)) {
            $this->childArticles[] = $childArticle;
            $childArticle->setCurrentLogisticUnit($this);
        }

        return $this;
    }

    public function removeChildArticle(Article $childArticle): self {
        if ($this->childArticles->removeElement($childArticle)) {
            if ($childArticle->getCurrentLogisticUnit() === $this) {
                $childArticle->setCurrentLogisticUnit(null);
            }
        }

        return $this;
    }

    public function setChildArticles(?iterable $childArticles): self {
        foreach($this->getChildArticles()->toArray() as $childArticle) {
            $this->removeChildArticle($childArticle);
        }

        $this->childArticles = new ArrayCollection();
        foreach($childArticles ?? [] as $childArticle) {
            $this->addChildArticle($childArticle);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProjectHistoryRecord>
     */
    public function getProjectHistoryRecords(): Collection {
        return $this->projectHistoryRecords;
    }

    public function addProjectHistoryRecord(ProjectHistoryRecord $projectHistoryRecord): self {
        if (!$this->projectHistoryRecords->contains($projectHistoryRecord)) {
            $this->projectHistoryRecords[] = $projectHistoryRecord;
            $projectHistoryRecord->setPack($this);
        }

        return $this;
    }

    public function removeProjectHistoryRecord(ProjectHistoryRecord $projectHistoryRecord): self {
        if ($this->projectHistoryRecords->removeElement($projectHistoryRecord)) {
            if ($projectHistoryRecord->getPack() === $this) {
                $projectHistoryRecord->setPack(null);
            }
        }

        return $this;
    }

    public function setProjectHistoryRecords(?iterable $projectHistoryRecords): self {
        foreach($this->getProjectHistoryRecords()->toArray() as $projectHistoryRecord) {
            $this->removeProjectHistoryRecord($projectHistoryRecord);
        }

        $this->projectHistoryRecords = new ArrayCollection();
        foreach($projectHistoryRecords ?? [] as $projectHistoryRecord) {
            $this->addProjectHistoryRecord($projectHistoryRecord);
        }

        return $this;
    }

    public function isArticleContainer(): ?bool
    {
        return $this->articleContainer;
    }

    public function setArticleContainer(?bool $articleContainer): self
    {
        $this->articleContainer = $articleContainer;
        return $this;
    }

    public function getShippingRequestPack(): ?ShippingRequestPack {
        return $this->shippingRequestPack;
    }

    public function setShippingRequestPack(?ShippingRequestPack $shippingRequestPack): self {
        if($this->shippingRequestPack && $this->shippingRequestPack->getPack() !== $this) {
            $oldShippingRequestPack = $this->shippingRequestPack;
            $this->shippingRequestPack = null;
            $oldShippingRequestPack->setPack(null);
        }
        $this->shippingRequestPack = $shippingRequestPack;
        if($this->shippingRequestPack && $this->shippingRequestPack->getPack() !== $this) {
            $this->shippingRequestPack->setPack($this);
        }

        return $this;
    }
}
