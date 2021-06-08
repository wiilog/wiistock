<?php

namespace App\Entity;

use App\Entity\IOT\SensorMessageTrait;
use App\Helper\FormatHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

use App\Entity\IOT\Pairing;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PackRepository")
 */
class Pack
{

    use SensorMessageTrait;

    public const PACK_IS_GROUP = 'PACK_IS_GROUP';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $code;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Arrivage", inversedBy="packs")
     */
    private $arrivage;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Litige", mappedBy="packs")
     */
    private $litiges;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Nature", inversedBy="packs")
     */
    private $nature;

    /**
     * @var TrackingMovement
     * @ORM\OneToOne(targetEntity=TrackingMovement::class, inversedBy="linkedPackLastDrop")
     * @ORM\JoinColumn(nullable=true)
     */
    private $lastDrop;

    /**
     * @var null|TrackingMovement
     * @ORM\OneToOne(targetEntity=TrackingMovement::class, inversedBy="linkedPackLastTracking")
     * @ORM\JoinColumn(nullable=true)
     */
    private $lastTracking;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity=TrackingMovement::class, mappedBy="pack")
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @ORM\OrderBy({"datetime" = "DESC", "id" = "DESC"})
     */
    private $trackingMovements;

    /**
     * @ORM\Column(type="integer", options={"default": 1})
     */
    private $quantity;

    /**
     * @ORM\Column(type="decimal", precision=12, scale=3, nullable=true)
     */
    private $weight;

    /**
     * @ORM\Column(type="decimal", precision=12, scale=3, nullable=true)
     */
    private $volume;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $groupIteration = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DispatchPack", mappedBy="pack", orphanRemoval=true)
     */
    private $dispatchPacks;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LocationClusterRecord", mappedBy="pack", cascade={"remove"})
     */
    private $locationClusterRecords;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Article", inversedBy="trackingPack")
     * @ORM\JoinColumn(nullable=true)
     */
    private $article;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="trackingPack")
     * @ORM\JoinColumn(nullable=true)
     */
    private $referenceArticle;

    /**
     * @ORM\ManyToOne(targetEntity=Pack::class, inversedBy="children")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Pack $parent = null;

    /**
     * @ORM\OneToMany(targetEntity=Pack::class, mappedBy="parent")
     */
    private ?Collection $children;

    /**
     * @ORM\OneToMany(targetEntity=TrackingMovement::class, mappedBy="packParent")
     */
    private ?Collection $childTrackingMovements;

    /**
     * @ORM\OneToMany(targetEntity=Pairing::class, mappedBy="pack")
     */
    private Collection $pairings;

    public function __construct() {
        $this->litiges = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
        $this->dispatchPacks = new ArrayCollection();
        $this->locationClusterRecords = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->childTrackingMovements = new ArrayCollection();
        $this->quantity = 1;
        $this->pairings = new ArrayCollection();
        $this->sensorMessages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string {
        return trim($this->code);
    }

    public function setCode(?string $code): self {
        $this->code = trim($code);
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

    /**
     * @return Collection|Litige[]
     */
    public function getLitiges(): Collection
    {
        return $this->litiges;
    }

    public function addLitige(Litige $litige): self
    {
        if (!$this->litiges->contains($litige)) {
            $this->litiges[] = $litige;
            $litige->addPack($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            $litige->removePack($this);
        }

        return $this;
    }

    public function getNature(): ?Nature
    {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self
    {
        $this->nature = $nature;

        return $this;
    }

    public function getLastDrop(): ?TrackingMovement
    {
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

    public function getLastTracking(): ?TrackingMovement
    {
        return $this->lastTracking;
    }

    public function setLastTracking(?TrackingMovement $lastTracking): self
    {
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
                'id' => $order
            ]);
        return $this->trackingMovements->matching($criteria);
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self
    {
        if (!$this->trackingMovements->contains($trackingMovement)) {
            // push on top new movement
            $trackingMovements = $this->trackingMovements->toArray();
            array_unshift($trackingMovements, $trackingMovement);
            $this->trackingMovements = new ArrayCollection($trackingMovements);

            $trackingMovement->setPack($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self
    {
        if ($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if ($trackingMovement->getPack() === $this) {
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

    public function addDispatchPack(DispatchPack $dispatchPack): self
    {
        if (!$this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks[] = $dispatchPack;
            $dispatchPack->setPack($this);
        }

        return $this;
    }

    public function removeDispatchPack(DispatchPack $dispatchPack): self
    {
        if ($this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks->removeElement($dispatchPack);
            // set the owning side to null (unless already changed)
            if ($dispatchPack->getPack() === $this) {
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
     * @return ArrayCollection
     */
    public function getLocationClusterRecords(): ArrayCollection {
        return $this->locationClusterRecords;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return self
     */
    public function addLocationClusterRecord(LocationClusterRecord $locationClusterRecord): self {
        if (!$this->locationClusterRecords->contains($locationClusterRecord)) {
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
        if ($this->locationClusterRecords->contains($locationClusterRecord)) {
            $this->locationClusterRecords->removeElement($locationClusterRecord);
            // set the owning side to null (unless already changed)
            if ($locationClusterRecord->getPack() === $this) {
                $locationClusterRecord->setPack(null);
            }
        }
        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        if ($this->article && $this->article->getTrackingPack() !== $this) {
            $oldArticle = $this->article;
            $this->article = null;
            $oldArticle->setTrackingPack(null);
        }
        $this->article = $article;
        if ($this->article && $this->article->getTrackingPack() !== $this) {
            $this->article->setTrackingPack($this);
        }
        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self {
        if (isset($this->referenceArticle)
            && $this->referenceArticle !== $referenceArticle) {
            $this->referenceArticle->setTrackingPack(null);
        }
        $this->referenceArticle = $referenceArticle;
        if (isset($this->referenceArticle)
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
        if ($this->parent
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
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(Pack $child): self {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
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
        if (!$this->childTrackingMovements->contains($movement)) {
            $this->childTrackingMovements[] = $movement;
            $movement->setPackParent($this);
        }

        return $this;
    }

    public function removeChildTrackingMovement(TrackingMovement $movement): self {
        if ($this->childTrackingMovements->removeElement($movement)) {
            if ($movement->getPackParent() === $this) {
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
            "date" => $lastTracking ? FormatHelper::datetime($lastTracking->getDatetime()) : ''
        ];
    }

    private function serializeGroup(): array {
        return [
            "id" => $this->getId(),
            "code" => $this->getCode(),
            "natureId" => $this->getNature() ? $this->getNature()->getId() : null,
            "packs" => $this->getChildren()
                ->map(fn(Pack $pack) => $pack->serialize())
                ->toArray()
        ];
    }

    /**
     * @return Collection|Pairing[]
     */
    public function getPairings(): Collection
    {
        return $this->pairings;
    }

    public function addPairing(Pairing $pairing): self
    {
        if (!$this->pairings->contains($pairing)) {
            $this->pairings[] = $pairing;
            $pairing->setPack($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self
    {
        if ($this->pairings->removeElement($pairing)) {
            // set the owning side to null (unless already changed)
            if ($pairing->getPack() === $this) {
                $pairing->setPack(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getCode();
    }
}
