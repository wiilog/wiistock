<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PackRepository")
 */
class Pack
{

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
     * @var MouvementTraca
     * @ORM\OneToOne(targetEntity="App\Entity\MouvementTraca", inversedBy="linkedPackLastDrop")
     */
    private $lastDrop;

    /**
     * @var MouvementTraca
     * @ORM\OneToOne(targetEntity="App\Entity\MouvementTraca", inversedBy="linkedPackLastTracking")
     */
    private $lastTracking;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementTraca", mappedBy="pack", cascade={"remove"})
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
     * @ORM\OneToMany(targetEntity="App\Entity\DispatchPack", mappedBy="pack", orphanRemoval=true)
     */
    private $dispatchPacks;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LocationClusterRecord", mappedBy="pack", orphanRemoval=true)
     */
    private $locationClusterRecords;

    public function __construct() {
        $this->litiges = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
        $this->dispatchPacks = new ArrayCollection();
        $this->locationClusterRecords = new ArrayCollection();
        $this->quantity = 1;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(?string $code): self {
        $this->code = $code;
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

    public function getLastDrop(): ?MouvementTraca
    {
        return $this->lastDrop;
    }

    public function setLastDrop(?MouvementTraca $lastDrop): self
    {
        if (isset($this->lastDrop)) {
            $this->lastDrop->setLinkedPackLastDrop(null);
        }
        $this->lastDrop = $lastDrop;
        if (isset($this->lastDrop)) {
            $this->lastDrop->setLinkedPackLastDrop($this);
        }

        return $this;
    }

    public function getLastTracking(): ?MouvementTraca
    {
        return $this->lastTracking;
    }

    public function setLastTracking(?MouvementTraca $lastDrop): self
    {
        if (isset($this->lastTracking)) {
            $this->lastTracking->setLinkedPackLastTracking(null);
        }
        $this->lastTracking = $lastDrop;

        if (isset($this->lastTracking)) {
            $this->lastTracking->setLinkedPackLastTracking($this);
        }
        return $this;
    }

    /**
     * @param string $order
     * @return Collection|MouvementTraca[]
     */
    public function getTrackingMovements(string $order = 'DESC'): Collection {
        $criteria = Criteria::create()
            ->orderBy([
                'datetime' => $order,
                'id' => $order
            ]);
        return $this->trackingMovements->matching($criteria);
    }

    public function addTrackingMovement(MouvementTraca $trackingMovement): self
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

    public function removeTrackingMovement(MouvementTraca $trackingMovement): self
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

}
