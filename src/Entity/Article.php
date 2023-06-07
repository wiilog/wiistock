<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryMission;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorMessageTrait;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\ArticleRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * @UniqueEntity("reference")
 */
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article implements PairedEntity {

    use SensorMessageTrait;
    use FreeFieldsManagerTrait;

    const CATEGORIE = 'article';
    const STATUT_ACTIF = 'disponible';
    const STATUT_INACTIF = 'consommé';
    const STATUT_EN_TRANSIT = 'en transit';
    const STATUT_EN_LITIGE = 'en litige';
    const USED_ASSOC_COLLECTE = 0;
    const USED_ASSOC_LITIGE = 1;
    const USED_ASSOC_INVENTORY = 2;
    const USED_ASSOC_STATUT_NOT_AVAILABLE = 3;
    const USED_ASSOC_PREPA_IN_PROGRESS = 4;
    const USED_ASSOC_TRANSFERT_REQUEST = 5;
    const USED_ASSOC_COLLECT_ORDER = 6;
    const USED_ASSOC_INVENTORY_ENTRY = 7;
    const BARCODE_PREFIX = 'ART';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'string', length: 15, nullable: true)]
    private ?string $barCode = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantite = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToMany(targetEntity: Collecte::class, mappedBy: 'articles')]
    private Collection $collectes;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'articles')]
    private ?Statut $statut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $inactiveSince = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $conform = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: MouvementStock::class)]
    private Collection $mouvements;

    #[ORM\ManyToOne(targetEntity: ArticleFournisseur::class, inversedBy: 'articles')]
    private ?ArticleFournisseur $articleFournisseur = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'articles')]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'articles')]
    private ?Emplacement $emplacement = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: DeliveryRequestArticleLine::class)]
    private Collection $deliveryRequestLines;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: PreparationOrderArticleLine::class)]
    private Collection $preparationOrderLines;

    #[ORM\ManyToOne(targetEntity: ReceptionReferenceArticle::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ReceptionReferenceArticle $receptionReferenceArticle = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: InventoryEntry::class)]
    private Collection $inventoryEntries;

    #[ORM\ManyToMany(targetEntity: InventoryMission::class, inversedBy: 'articles')]
    private Collection $inventoryMissions;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $prixUnitaire = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $dateLastInventory = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $lastAvailableDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $firstUnavailableDate = null;

    #[ORM\ManyToMany(targetEntity: OrdreCollecte::class, inversedBy: 'articles')]
    private Collection $ordreCollecte;

    #[ORM\ManyToMany(targetEntity: Dispute::class, mappedBy: 'articles', cascade: ['remove'])]
    private Collection $disputes;

    #[ORM\OneToOne(mappedBy: 'article', targetEntity: Pack::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Pack $trackingPack = null;

    #[ORM\ManyToMany(targetEntity: TransferRequest::class, mappedBy: 'articles')]
    private Collection $transferRequests;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: Alert::class, cascade: ['remove'])]
    private Collection $alerts;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $batch = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $expiryDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $stockEntryDate = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: Pairing::class, cascade: ['remove'])]
    private Collection $pairings;

    #[ORM\ManyToMany(targetEntity: Cart::class, mappedBy: 'articles')]
    private ?Collection $carts;

    #[ORM\ManyToOne(targetEntity: Pack::class, cascade: ['persist'], inversedBy: "childArticles")]
    private ?Pack $currentLogisticUnit = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $createdOnKioskAt = null;

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true)]
    private ?string $RFIDtag = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $deliveryNote = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $purchaseOrder = null;

    #[ORM\ManyToOne(targetEntity: NativeCountry::class)]
    private ?NativeCountry $nativeCountry = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $manifacturingDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $productionDate = null;

    public function __construct() {
        $this->deliveryRequestLines = new ArrayCollection();
        $this->preparationOrderLines = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->inventoryEntries = new ArrayCollection();
        $this->inventoryMissions = new ArrayCollection();
        $this->disputes = new ArrayCollection();
        $this->ordreCollecte = new ArrayCollection();
        $this->transferRequests = new ArrayCollection();

        $this->quantite = 0;
        $this->alerts = new ArrayCollection();
        $this->pairings = new ArrayCollection();
        $this->sensorMessages = new ArrayCollection();
        $this->carts = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getReference(): ?string {
        return $this->reference;
    }

    public function setReference(?string $reference): self {
        $this->reference = $reference;

        return $this;
    }

    public function getQuantite(): ?int {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self {
        $this->quantite = $quantite;

        return $this;
    }

    public function __toString(): string {
        return $this->barCode;
    }

    public function getCommentaire(): ?string {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * @return Collection|Collecte[]
     */
    public function getCollectes(): Collection {
        return $this->collectes;
    }

    public function addCollecte(Collecte $collecte): self {
        if(!$this->collectes->contains($collecte)) {
            $this->collectes[] = $collecte;
            $collecte->addArticle($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self {
        if($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            $collecte->removeArticle($this);
        }

        return $this;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

        return $this;
    }

    public function getInactiveSince(): ?DateTime {
        return $this->inactiveSince;
    }

    public function setInactiveSince(?DateTime $inactiveSince): self {
        $this->inactiveSince = $inactiveSince;
        return $this;
    }

    public function getConform(): ?bool {
        return $this->conform;
    }

    public function setConform(bool $conform): self {
        $this->conform = $conform;

        return $this;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getArticleFournisseur(): ?ArticleFournisseur {
        return $this->articleFournisseur;
    }

    public function setArticleFournisseur(?ArticleFournisseur $articleFournisseur): self {
        $this->articleFournisseur = $articleFournisseur;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getEmplacement(): ?Emplacement {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): self {
        $this->emplacement = $emplacement;
        return $this;
    }

    /**
     * @return Collection|DeliveryRequestArticleLine[]
     */
    public function getDeliveryRequestLines(): Collection {
        return $this->deliveryRequestLines;
    }

    public function addDeliveryRequestLine(DeliveryRequestArticleLine $line): self {
        if(!$this->deliveryRequestLines->contains($line)) {
            $this->deliveryRequestLines[] = $line;
            $line->setArticle($this);
        }

        return $this;
    }

    public function removeDeliveryRequestLine(DeliveryRequestArticleLine $line): self {
        if($this->deliveryRequestLines->removeElement($line)) {
            if($line->getArticle() === $this) {
                $line->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|PreparationOrderArticleLine[]
     */
    public function getPreparationOrderLines(): Collection {
        return $this->preparationOrderLines;
    }

    public function addPreparationOrderLine(PreparationOrderArticleLine $line): self {
        if(!$this->preparationOrderLines->contains($line)) {
            $this->preparationOrderLines[] = $line;
            $line->setArticle($this);
        }

        return $this;
    }

    public function removePreparationOrderLine(PreparationOrderArticleLine $line): self {
        if($this->preparationOrderLines->removeElement($line)) {
            if($line->getArticle() === $this) {
                $line->setArticle(null);
            }
        }

        return $this;
    }

    public function getPrixUnitaire() {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire($prixUnitaire): self {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    /**
     * @return Selectable|Collection|MouvementStock[]
     */
    public function getMouvements() {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self {
        if(!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setArticle($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getArticle() === $this) {
                $mouvement->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|InventoryEntry[]
     */
    public function getInventoryEntries(): Collection {
        return $this->inventoryEntries;
    }

    public function addInventoryEntry(InventoryEntry $inventoryEntry): self {
        if(!$this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries[] = $inventoryEntry;
            $inventoryEntry->setArticle($this);
        }

        return $this;
    }

    public function removeInventoryEntry(InventoryEntry $inventoryEntry): self {
        if($this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries->removeElement($inventoryEntry);
            // set the owning side to null (unless already changed)
            if($inventoryEntry->getArticle() === $this) {
                $inventoryEntry->setArticle(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|InventoryMission[]
     */
    public function getInventoryMissions(): Collection {
        return $this->inventoryMissions;
    }

    public function addInventoryMission(InventoryMission $inventoryMission): self {
        if(!$this->inventoryMissions->contains($inventoryMission)) {
            $this->inventoryMissions[] = $inventoryMission;
        }

        return $this;
    }

    public function removeInventoryMission(InventoryMission $inventoryMission): self {
        if($this->inventoryMissions->contains($inventoryMission)) {
            $this->inventoryMissions->removeElement($inventoryMission);
        }

        return $this;
    }

    public function getDateLastInventory(): ?\DateTime {
        return $this->dateLastInventory;
    }

    public function setDateLastInventory(?\DateTime $dateLastInventory): self {
        $this->dateLastInventory = $dateLastInventory;

        return $this;
    }

    public function getLastAvailableDate(): ?\DateTime {
        return $this->lastAvailableDate;
    }

    public function setLastAvailableDate(?\DateTime $lastAvailableDate): self {
        $this->lastAvailableDate = $lastAvailableDate;

        return $this;
    }

    public function getFirstUnavailableDate(): ?\DateTime {
        return $this->firstUnavailableDate;
    }

    public function setFirstUnavailableDate(?\DateTime $firstUnavailableDate): self {
        $this->firstUnavailableDate = $firstUnavailableDate;

        return $this;
    }

    public function getBarCode(): ?string {
        return $this->barCode;
    }

    public function setBarCode(?string $barCode): self {
        $this->barCode = $barCode;

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
            $dispute->addArticle($this);
        }

        return $this;
    }

    public function removeDispute(Dispute $dispute): self {
        if($this->disputes->contains($dispute)) {
            $this->disputes->removeElement($dispute);
            $dispute->removeArticle($this);
        }

        return $this;
    }

    /**
     * @return Collection|OrdreCollecte[]
     */
    public function getOrdreCollecte(): Collection {
        return $this->ordreCollecte;
    }

    public function addOrdreCollecte(OrdreCollecte $ordreCollecte): self {
        if(!$this->ordreCollecte->contains($ordreCollecte)) {
            $this->ordreCollecte[] = $ordreCollecte;
        }

        return $this;
    }

    public function removeOrdreCollecte(OrdreCollecte $ordreCollecte): self {
        if($this->ordreCollecte->contains($ordreCollecte)) {
            $this->ordreCollecte->removeElement($ordreCollecte);
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

    /**
     * @return null|Pack
     */
    public function getTrackingPack(): ?Pack {
        return $this->trackingPack;
    }

    /**
     * @param Pack|null
     * @return Article
     */
    public function setTrackingPack(?Pack $pack): self {
        if($this->trackingPack && $this->trackingPack->getArticle() !== $this) {
            $oldTrackingPack = $this->trackingPack;
            $this->trackingPack = null;
            $oldTrackingPack->setArticle(null);
        }
        $this->trackingPack = $pack;
        if($this->trackingPack && $this->trackingPack->getArticle() !== $this) {
            $this->trackingPack->setArticle($this);
        }
        return $this;
    }

    public function getTrackingMovements(): Collection {
        return isset($this->trackingPack)
            ? $this->trackingPack->getTrackingMovements()
            : new ArrayCollection();
    }


    /**
     * @return int|null
     */
    public function getUsedAssociation(): ?int {
        return (
        (!$this->getCollectes()->isEmpty())
            ? self::USED_ASSOC_COLLECTE
            : ((!$this->getDisputes()->isEmpty())
            ? self::USED_ASSOC_LITIGE
            : ((!$this->getInventoryEntries()->isEmpty())
                ? self::USED_ASSOC_INVENTORY
                : ($this->getStatut()->getCode() === self::STATUT_INACTIF
                    ? self::USED_ASSOC_STATUT_NOT_AVAILABLE
                    : ((!$this->getPreparationOrderLines()->isEmpty())
                        ? self::USED_ASSOC_PREPA_IN_PROGRESS
                        : ((!$this->getTransferRequests()->isEmpty())
                            ? self::USED_ASSOC_TRANSFERT_REQUEST
                            : ((!$this->getOrdreCollecte()->isEmpty())
                                ? self::USED_ASSOC_COLLECT_ORDER
                                : ((!$this->getInventoryEntries()->isEmpty())
                                    ? self::USED_ASSOC_INVENTORY_ENTRY
                                    : null
                                )
                            )
                        )
                    )
                )
            )
        )

        );
    }

    /**
     * @return Collection|TransferRequest[]
     */
    public function getTransferRequests(): Collection {
        return $this->transferRequests;
    }

    public function addTransferRequest(TransferRequest $transferRequest): self {
        if(!$this->transferRequests->contains($transferRequest)) {
            $this->transferRequests[] = $transferRequest;
            $transferRequest->addArticle($this);
        }

        return $this;
    }

    public function removeTransferRequest(TransferRequest $transferRequest): self {
        if($this->transferRequests->contains($transferRequest)) {
            $this->transferRequests->removeElement($transferRequest);
            $transferRequest->removeArticle($this);
        }

        return $this;
    }

    /**
     * @return Collection|Alert[]
     */
    public function getAlerts(): Collection {
        return $this->alerts;
    }

    public function addAlert(Alert $alert): self {
        if(!$this->alerts->contains($alert)) {
            $this->alerts[] = $alert;
            $alert->setArticle($this);
        }

        return $this;
    }

    public function removeAlert(Alert $alert): self {
        if($this->alerts->contains($alert)) {
            $this->alerts->removeElement($alert);
            // set the owning side to null (unless already changed)
            if($alert->getArticle() === $this) {
                $alert->setArticle(null);
            }
        }

        return $this;
    }

    public function isExpired(): ?bool {
        if($this->getExpiryDate()) {
            $now = new DateTime("now");

            return $now >= $this->getExpiryDate();
        } else {
            return null;
        }
    }

    public function getExpiryDate(): ?\DateTimeInterface {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): self {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getBatch(): ?string {
        return $this->batch;
    }

    public function setBatch(?string $batch): self {
        $this->batch = $batch;

        return $this;
    }

    public function getStockEntryDate(): ?\DateTimeInterface {
        return $this->stockEntryDate;
    }

    public function setStockEntryDate(?\DateTimeInterface $stockEntryDate): self {
        $this->stockEntryDate = $stockEntryDate;

        return $this;
    }

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
            $pairing->setArticle($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self {
        if($this->pairings->removeElement($pairing)) {
            // set the owning side to null (unless already changed)
            if($pairing->getArticle() === $this) {
                $pairing->setArticle(null);
            }
        }

        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        $supplierArticle = $this->getArticleFournisseur();
        return $supplierArticle?->getReferenceArticle();
    }

    public function getCarts(): Collection {
        return $this->carts;
    }

    public function addCart(Cart $cart): self {
        if(!$this->carts->contains($cart)) {
            $this->carts[] = $cart;
            $cart->addArticle($this);
        }

        return $this;
    }

    public function getCreatedOnKioskAt(): ?\DateTimeInterface {
        return $this->createdOnKioskAt;
    }

    public function setCreatedOnKioskAt(?\DateTimeInterface $createdOnKioskAt): self {
        $this->createdOnKioskAt = $createdOnKioskAt;

        return $this;
    }

    public function removeCart(Cart $cart): self {
        if($this->carts->removeElement($cart)) {
            $cart->removeArticle($this);
        }

        return $this;
    }

    public function getCurrentLogisticUnit(): ?Pack {
        return $this->currentLogisticUnit;
    }

    public function setCurrentLogisticUnit(?Pack $currentLogisticUnit): self {
        if($this->currentLogisticUnit && $this->currentLogisticUnit !== $currentLogisticUnit) {
            $this->currentLogisticUnit->removeChildArticle($this);
        }
        $this->currentLogisticUnit = $currentLogisticUnit;
        $currentLogisticUnit?->addChildArticle($this);

        return $this;
    }

    public function isInTransit(): bool {
        return $this->getStatut()->getCode() === self::STATUT_EN_TRANSIT;
    }

    public function getRFIDtag(): ?string {
        return $this->RFIDtag;
    }

    public function setRFIDtag(?string $RFIDtag): self
    {
        $this->RFIDtag = $RFIDtag;

        return $this;
    }

    public function getDeliveryNote(): ?string
    {
        return $this->deliveryNote;
    }

    public function setDeliveryNote(?string $deliveryNote): self
    {
        $this->deliveryNote = $deliveryNote;

        return $this;
    }

    public function getPurchaseOrder(): ?string
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(?string $purchaseOrder): self
    {
        $this->purchaseOrder = $purchaseOrder;

        return $this;
    }

    public function getNativeCountry(): ?NativeCountry
    {
        return $this->nativeCountry;
    }

    public function setNativeCountry(?NativeCountry $nativeCountry): self
    {
        $this->nativeCountry = $nativeCountry;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getManifacturingDate(): ?DateTime
    {
        return $this->manifacturingDate;
    }

    public function setManifacturingDate(?DateTime $manifacturingDate): self
    {
        $this->manifacturingDate = $manifacturingDate;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getProductionDate(): ?DateTime
    {
        return $this->productionDate;
    }

    public function setProductionDate(?DateTime $productionDate): self
    {
        $this->productionDate = $productionDate;

        return $this;
    }
}
