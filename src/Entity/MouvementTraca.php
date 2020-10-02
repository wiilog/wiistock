<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MouvementTracaRepository")
 */
class MouvementTraca extends FreeFieldEntity
{

    const TYPE_PRISE = 'prise';
    const TYPE_DEPOSE = 'depose';
    const TYPE_PRISE_DEPOSE = 'prises et deposes';
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var Pack
     * @ORM\ManyToOne(targetEntity="App\Entity\Pack", inversedBy="trackingMovements")
     * @ORM\JoinColumn(nullable=false, name="pack_id")
     */
    private $pack;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uniqueIdForMobile;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", length=255, nullable=true)
     */
    private $datetime;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement")
     */
    private $emplacement;

    /**
     * @var Statut|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut")
     */
    private $type;

    /**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur")
     */
    private $operateur;

    /**
     * @var MouvementStock|null
	 * @ORM\ManyToOne(targetEntity="App\Entity\MouvementStock")
     * @ORM\JoinColumn(name="mouvement_stock_id", referencedColumnName="id", nullable=true)
     */
    private $mouvementStock;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $commentaire;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\PieceJointe", mappedBy="mouvementTraca")
	 */
	private $attachements;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $finished;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=false, options={"default": 1})
     */
    private $quantity;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="mouvementsTraca")
     */
    private $reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Arrivage", inversedBy="mouvementsTraca")
     */
    private $arrivage;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Dispatch", inversedBy="trackingMovements")
     */
    private $dispatch;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="mouvementTracas")
     */
    private $referenceArticle;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", inversedBy="mouvementTracas")
     */
    private $article;

    /**
     * @var Pack|null
     * @ORM\OneToOne (targetEntity="App\Entity\Pack", mappedBy="lastDrop")
     */
    private $linkedPackLastDrop;

    /**
     * @var Pack|null
     * @ORM\OneToOne(targetEntity="App\Entity\Pack", mappedBy="lastTracking")
     */
    private $linkedPackLastTracking;

    /**
     * @var ArrayCollection|null
     * @ORM\OneToMany(targetEntity="App\Entity\LocationClusterRecord", mappedBy="firstDrop")
     */
    private $firstDropRecords;
    /**
     * @var ArrayCollection|null
     * @ORM\OneToMany(targetEntity="App\Entity\LocationClusterRecord", mappedBy="lastTracking")
     */
    private $lastTrackingRecords;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReceptionReferenceArticle", inversedBy="mouvementsTraca")
     */
    private $receptionReferenceArticle;

    public function __construct()
    {
        $this->quantity = 1;
        $this->attachements = new ArrayCollection();
        $this->firstDropRecords = new ArrayCollection();
        $this->lastTrackingRecords = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUniqueIdForMobile(): ?string
    {
        return $this->uniqueIdForMobile;
    }

    public function setUniqueIdForMobile(?string $uniqueIdForMobile): self
    {
        $this->uniqueIdForMobile = $uniqueIdForMobile;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getEmplacement(): ?Emplacement {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    /**
     * @return Collection|PieceJointe[]
     */
    public function getAttachments(): Collection
    {
        return $this->attachements;
    }

    public function addAttachment(PieceJointe $attachment): self
    {
        if (!$this->attachements->contains($attachment)) {
            $this->attachements[] = $attachment;
            $attachment->setMouvementTraca($this);
        }

        return $this;
    }

    public function removeAttachment(PieceJointe $attachement): self
    {
        if ($this->attachements->contains($attachement)) {
            $this->attachements->removeElement($attachement);
            // set the owning side to null (unless already changed)
            if ($attachement->getMouvementTraca() === $this) {
                $attachement->setMouvementTraca(null);
            }
        }

        return $this;
    }

    public function getOperateur(): ?Utilisateur
    {
        return $this->operateur;
    }

    public function setOperateur(?Utilisateur $operateur): self
    {
        $this->operateur = $operateur;

        return $this;
    }

    public function getType(): ?Statut
    {
        return $this->type;
    }

    public function isDrop(): bool
    {
        return (
            $this->type
            && $this->type->getNom() === self::TYPE_DEPOSE
        );
    }

    public function isTaking(): bool
    {
        return (
            $this->type
            && $this->type->getNom() === self::TYPE_PRISE
        );
    }

    public function setType(?Statut $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDatetime(): ?DateTime
    {
        return $this->datetime;
    }

    public function setDatetime(?DateTime $datetime): self
    {
        $this->datetime = $datetime;

        return $this;
    }

    public function isFinished(): ?bool
    {
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

    public function getFinished(): ?bool
    {
        return $this->finished;
    }

    public function getReception(): ?Reception
    {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self
    {
        $this->reception = $reception;

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

    public function getDispatch()
    {
        return $this->dispatch;
    }

    /**
     * @param mixed $dispatch
     * @return MouvementTraca
     */
    public function setDispatch($dispatch): self
    {
        $this->dispatch = $dispatch;
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

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;

        return $this;
    }

    /**
     * @return Pack|null
     */
    public function getLinkedPackLastTracking(): ?Pack {
        return $this->linkedPackLastTracking;
    }

    /**
     * @param Pack|null $linkedPackLastTracking
     * @return MouvementTraca
     */
    public function setLinkedPackLastTracking(?Pack $linkedPackLastTracking): MouvementTraca {
        $this->linkedPackLastTracking = $linkedPackLastTracking;
        return $this;
    }

    /**
     * @return Pack|null
     */
    public function getLinkedPackLastDrop(): ?Pack {
        return $this->linkedPackLastDrop;
    }

    /**
     * @param Pack|null $linkedPackLastDrop
     * @return MouvementTraca
     */
    public function setLinkedPackLastDrop(?Pack $linkedPackLastDrop): MouvementTraca {
        $this->linkedPackLastDrop = $linkedPackLastDrop;
        return $this;
    }

    public function getReceptionReferenceArticle(): ?ReceptionReferenceArticle
    {
        return $this->receptionReferenceArticle;
    }

    public function setReceptionReferenceArticle(?ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        $this->receptionReferenceArticle = $receptionReferenceArticle;

        return $this;
    }

    public function setPack(Pack $pack): self {
        $this->pack = $pack;
        $this->pack->setLastTracking($this);
        return $this;
    }

    public function getPack(): Pack {
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
    public function addFirstDropRecord(LocationClusterRecord $recored): self
    {
        if (!$this->firstDropRecords->contains($recored)) {
            $this->firstDropRecords[] = $recored;
        }

        return $this;
    }

    /**
     * @param LocationClusterRecord $record
     * @return $this
     */
    public function removeFirstDropRecord(LocationClusterRecord $record): self
    {
        if ($this->firstDropRecords->contains($record)) {
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
    public function addLastTrackingRecord(LocationClusterRecord $record): self
    {
        if (!$this->lastTrackingRecords->contains($record)) {
            $this->lastTrackingRecords[] = $record;
        }

        return $this;
    }

    /**
     * @param LocationClusterRecord $record
     * @return $this
     */
    public function removeLastTrackingRecord(LocationClusterRecord $record): self
    {
        if ($this->lastTrackingRecords->contains($record)) {
            $this->lastTrackingRecords->removeElement($record);
        }

        return $this;
    }
}
