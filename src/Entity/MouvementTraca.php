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
     * TODO REMOVE
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $colis;

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
     * @ORM\OneToMany(targetEntity="App\Entity\Pack", mappedBy="lastDrop")
     */
    private $linkedPackLastDrops;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Pack", mappedBy="lastTracking")
     */
    private $linkedPackLastTrackings;

    /**
     * @var ArrayCollection|null
     * @ORM\OneToMany(targetEntity="App\Entity\LocationClusterRecord", mappedBy="firstDrop")
     */
    private $firstDropsOnLocationCluster;
    /**
     * @var ArrayCollection|null
     * @ORM\OneToMany(targetEntity="App\Entity\LocationClusterRecord", mappedBy="lastTracking")
     */
    private $lastTrackingOnLocationCluster;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReceptionReferenceArticle", inversedBy="mouvementsTraca")
     */
    private $receptionReferenceArticle;

    public function __construct()
    {
        $this->quantity = 1;
        $this->attachements = new ArrayCollection();
        $this->linkedPackLastDrops = new ArrayCollection();
        $this->linkedPackLastTrackings = new ArrayCollection();
        $this->firstDropsOnLocationCluster = new ArrayCollection();
        $this->lastTrackingOnLocationCluster = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getColis(): ?string
    {
        return $this->colis;
    }

    public function setColis(?string $colis): self
    {
        $this->colis = $colis;

        return $this;
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
     * @return Collection|Pack[]
     */
    public function getLinkedPackLastTracking(): Collection
    {
        return $this->linkedPackLastTrackings;
    }

    /**
     * @return Collection|Pack[]
     */
    public function getLinkedPackLastDrops(): Collection
    {
        return $this->linkedPackLastDrops;
    }

    public function addLinkedPackLastDrop(Pack $linkedPackLastDrop): self
    {
        if (!$this->linkedPackLastDrops->contains($linkedPackLastDrop)) {
            $this->linkedPackLastDrops[] = $linkedPackLastDrop;
        }

        return $this;
    }

    public function removeLinkedPackLastDrop(Pack $linkedPackLastDrop): self
    {
        if ($this->linkedPackLastDrops->contains($linkedPackLastDrop)) {
            $this->linkedPackLastDrops->removeElement($linkedPackLastDrop);
        }

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
    public function getFirstDropsOnLocationCluster(): ?Collection {
        return $this->firstDropsOnLocationCluster;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return $this
     */
    public function addFirstDropOnLocationCluster(LocationClusterRecord $locationClusterRecord): self
    {
        if (!$this->firstDropsOnLocationCluster->contains($locationClusterRecord)) {
            $this->firstDropsOnLocationCluster[] = $locationClusterRecord;
        }

        return $this;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return $this
     */
    public function removeFirstDropOnLocationCluster(LocationClusterRecord $locationClusterRecord): self
    {
        if ($this->firstDropsOnLocationCluster->contains($locationClusterRecord)) {
            $this->firstDropsOnLocationCluster->removeElement($locationClusterRecord);
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getLastTrackingOnLocationCluster(): ?Collection {
        return $this->lastTrackingOnLocationCluster;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return $this
     */
    public function addLastTrackingOnLocationCluster(LocationClusterRecord $locationClusterRecord): self
    {
        if (!$this->lastTrackingOnLocationCluster->contains($locationClusterRecord)) {
            $this->lastTrackingOnLocationCluster[] = $locationClusterRecord;
        }

        return $this;
    }

    /**
     * @param LocationClusterRecord $locationClusterRecord
     * @return $this
     */
    public function removeLastTrackingOnLocationCluster(LocationClusterRecord $locationClusterRecord): self
    {
        if ($this->lastTrackingOnLocationCluster->contains($locationClusterRecord)) {
            $this->lastTrackingOnLocationCluster->removeElement($locationClusterRecord);
        }

        return $this;
    }
}
