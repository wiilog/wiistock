<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\Interfaces\AttachmentContainer;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryCategoryHistory;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryMission;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Tracking\Pack;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Traits\CleanedCommentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Entity\Traits\LastMovementTrait;
use App\Entity\Traits\LitePropertiesSetterTrait;
use App\Entity\Type\Type;
use App\Repository\ReferenceArticleRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: ReferenceArticleRepository::class)]
class ReferenceArticle implements AttachmentContainer {

    use FreeFieldsManagerTrait;
    use AttachmentTrait;
    use CleanedCommentTrait;
    use LitePropertiesSetterTrait;
    use LastMovementTrait;

    const CATEGORIE = 'referenceArticle';
    const STATUT_ACTIF = 'actif';
    const STATUT_INACTIF = 'inactif';
    const DRAFT_STATUS = 'brouillon';
    const QUANTITY_TYPE_REFERENCE = 'reference';
    const QUANTITY_TYPE_ARTICLE = 'article';
    const BARCODE_PREFIX = 'REF';
    const STOCK_MANAGEMENT_FEFO = 'FEFO';
    const STOCK_MANAGEMENT_FIFO = 'FIFO';
    const DEFAULT_STOCK_MANAGEMENT = self::STOCK_MANAGEMENT_FEFO;
    const PURCHASE_IN_PROGRESS_ORDER_STATE = "purchaseInProgress";
    const WAIT_FOR_RECEPTION_ORDER_STATE = "waitForReception";
    const MAX_NOMADE_SYNC = 4000;

    public const TO_DEFINE_LABEL = "A DEFINIR";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::STRING, length: 15, unique: true, nullable: false)]
    private ?string $barCode = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $quantiteDisponible;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $quantiteReservee;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $quantiteStock;

    /**
     * @return Collection<int, DeliveryRequestReferenceLine>
     */
    #[ORM\OneToMany(mappedBy: 'reference', targetEntity: DeliveryRequestReferenceLine::class)]
    private Collection $deliveryRequestLines;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'referenceArticles')]
    private ?Type $type = null;

    /**
     * @return Collection<int, ArticleFournisseur>
     */
    #[ORM\OneToMany(mappedBy: 'referenceArticle', targetEntity: ArticleFournisseur::class)]
    private Collection $articlesFournisseur;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: true)]
    private ?string $typeQuantite = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'referenceArticles')]
    private ?Statut $statut = null;

    /**
     * @return Collection<int, CollecteReference>
     */
    #[ORM\OneToMany(mappedBy: 'referenceArticle', targetEntity: CollecteReference::class)]
    private Collection $collecteReferences;

    /**
     * @return Collection<int, OrdreCollecteReference>
     */
    #[ORM\OneToMany(mappedBy: 'referenceArticle', targetEntity: OrdreCollecteReference::class)]
    private Collection $ordreCollecteReferences;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    /**
     * @return Collection<int, ReceptionReferenceArticle>
     */
    #[ORM\OneToMany(mappedBy: 'referenceArticle', targetEntity: ReceptionReferenceArticle::class)]
    private Collection $receptionReferenceArticles;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'referenceArticles')]
    private ?Emplacement $emplacement = null;

    /**
     * @return Collection<int, MouvementStock>
     */
    #[ORM\OneToMany(mappedBy: 'refArticle', targetEntity: MouvementStock::class)]
    private Collection $mouvements;

    #[ORM\ManyToOne(targetEntity: InventoryCategory::class)]
    private ?InventoryCategory $category = null;

    /**
     * @return Collection<int, InventoryEntry>
     */
    #[ORM\OneToMany(mappedBy: 'refArticle', targetEntity: InventoryEntry::class)]
    private Collection $inventoryEntries;

    /**
     * @return Collection<int, InventoryCategoryHistory>
     */
    #[ORM\OneToMany(mappedBy: 'refArticle', targetEntity: InventoryCategoryHistory::class)]
    private Collection $inventoryCategoryHistory;

    /**
     * @return Collection<int, InventoryMission>
     */
    #[ORM\ManyToMany(targetEntity: InventoryMission::class, mappedBy: 'refArticles')]
    private Collection $inventoryMissions;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $prixUnitaire = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $dateLastInventory = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $limitSecurity = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $limitWarning = null;

    /**
     * @return Collection<int, PreparationOrderReferenceLine>
     */
    #[ORM\OneToMany(mappedBy: 'reference', targetEntity: PreparationOrderReferenceLine::class)]
    private Collection $preparationOrderReferenceLines;

    #[ORM\OneToOne(mappedBy: 'referenceArticle', targetEntity: Pack::class)]
    private ?Pack $trackingPack = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $needsMobileSync = null;

    /**
     * @return Collection<int, TransferRequest>
     */
    #[ORM\ManyToMany(targetEntity: TransferRequest::class, mappedBy: 'references')]
    private Collection $transferRequests;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $stockManagement = null;

    /**
     * @return Collection<int, Utilisateur>
     */
    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $managers;

    /**
     * @return Collection<int, >
     */
    #[ORM\OneToMany(mappedBy: 'reference', targetEntity: Alert::class, cascade: ['remove'])]
    private Collection $alerts;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'referencesBuyer')]
    private ?Utilisateur $buyer = null;

    /**
     * @return Collection<int, Cart>
     */
    #[ORM\ManyToMany(targetEntity: Cart::class, mappedBy: 'references')]
    private ?Collection $carts;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $orderState = null;

    /**
     * @return Collection<int, PurchaseRequestLine>
     */
    #[ORM\OneToMany(mappedBy: 'reference', targetEntity: PurchaseRequestLine::class)]
    private Collection $purchaseRequestLines;

    #[ORM\ManyToOne(targetEntity: VisibilityGroup::class, inversedBy: 'articleReferences')]
    private ?VisibilityGroup $visibilityGroup = null;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $image = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $editedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $editedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $lastStockEntry = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $lastStockExit = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $upToDateInventory;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $description = [];

    /**
     * @return Collection<int, StorageRule>
     */
    #[ORM\OneToMany(mappedBy: 'referenceArticle', targetEntity: StorageRule::class, orphanRemoval: true)]
    private Collection $storageRules;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $ndpCode = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $onuCode = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $productClass = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $dangerousGoods;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $sheet = null;

    /**
     * the null value mean 'never responded'.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastSleepingStockAlertAnswer = null;

    public function __construct() {
        $this->deliveryRequestLines = new ArrayCollection();
        $this->articlesFournisseur = new ArrayCollection();
        $this->collecteReferences = new ArrayCollection();
        $this->receptionReferenceArticles = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->inventoryEntries = new ArrayCollection();
        $this->inventoryCategoryHistory = new ArrayCollection();
        $this->inventoryMissions = new ArrayCollection();
        $this->ordreCollecteReferences = new ArrayCollection();
        $this->preparationOrderReferenceLines = new ArrayCollection();
        $this->managers = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->transferRequests = new ArrayCollection();
        $this->alerts = new ArrayCollection();
        $this->carts = new ArrayCollection();
        $this->purchaseRequestLines = new ArrayCollection();
        $this->storageRules = new ArrayCollection();

        $this->quantiteStock = 0;
        $this->quantiteReservee = 0;
        $this->quantiteDisponible = 0;
        $this->upToDateInventory = false;
        $this->dangerousGoods = false;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLibelle(): ?string {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self {
        $this->libelle = $libelle;

        return $this;
    }

    public function getReference(): ?string {
        return $this->reference;
    }

    public function setReference(?string $reference): self {
        $this->reference = $reference;
        return $this;
    }

    public function __toString(): string {
        return $this->reference;
    }

    public function getQuantiteDisponible(): ?int {
        return $this->quantiteDisponible;
    }

    public function setQuantiteDisponible(?int $quantiteDisponible): self {
        $this->quantiteDisponible = $quantiteDisponible;

        return $this;
    }

    public function getQuantiteReservee(): ?int {
        return $this->quantiteReservee ?? 0;
    }

    public function setQuantiteReservee(?int $quantiteReservee): self {
        $this->quantiteReservee = $quantiteReservee;

        return $this;
    }

    public function getQuantiteStock(): int {
        return $this->quantiteStock ?? 0;
    }

    public function setQuantiteStock(?int $quantiteStock): self {
        $this->quantiteStock = $quantiteStock;

        return $this;
    }

    /**
     * @return Collection<int, DeliveryRequestReferenceLine>
     */
    public function getDeliveryRequestLines(): Collection {
        return $this->deliveryRequestLines;
    }

    public function addDeliveryRequestReferenceLine(DeliveryRequestReferenceLine $line): self {
        if(!$this->deliveryRequestLines->contains($line)) {
            $this->deliveryRequestLines[] = $line;
            $line->setReference($this);
        }

        return $this;
    }

    public function removeDeliveryRequestReferenceLine(DeliveryRequestReferenceLine $line): self {
        if($this->deliveryRequestLines->contains($line)) {
            $this->deliveryRequestLines->removeElement($line);
            // set the owning side to null (unless already changed)
            if($line->getReference() === $this) {
                $line->setReference(null);
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

    /**
     * @return Collection<int, ArticleFournisseur>
     */
    public function getArticlesFournisseur(): Collection {
        return $this->articlesFournisseur;
    }

    public function addArticleFournisseur(ArticleFournisseur $articlesFournisseur): self {
        if(!$this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur[] = $articlesFournisseur;
            $articlesFournisseur->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeArticleFournisseur(ArticleFournisseur $articlesFournisseur): self {
        if($this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur->removeElement($articlesFournisseur);
            // set the owning side to null (unless already changed)
            if($articlesFournisseur->getReferenceArticle() === $this) {
                $articlesFournisseur->setReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getTypeQuantite(): ?string {
        return $this->typeQuantite;
    }

    public function setTypeQuantite(?string $typeQuantite): self {
        $this->typeQuantite = $typeQuantite;

        return $this;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return Collection<int, CollecteReference>
     */
    public function getCollecteReferences(): Collection {
        return $this->collecteReferences;
    }

    public function addCollecteReference(CollecteReference $collecteReference): self {
        if(!$this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences[] = $collecteReference;
            $collecteReference->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeCollecteReference(CollecteReference $collecteReference): self {
        if($this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences->removeElement($collecteReference);
            // set the owning side to null (unless already changed)
            if($collecteReference->getReferenceArticle() === $this) {
                $collecteReference->setReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?string {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self {
        $this->commentaire = $commentaire;
        $this->setCleanedComment($commentaire);

        return $this;
    }

    /**
     * @return Collection<int,ReceptionReferenceArticle>
     */
    public function getReceptionReferenceArticles(): Collection {
        return $this->receptionReferenceArticles;
    }

    public function addReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self {
        if(!$this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles[] = $receptionReferenceArticle;
            $receptionReferenceArticle->setReferenceArticle($this);
        }
        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self {
        if($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if($receptionReferenceArticle->getReferenceArticle() === $this) {
                $receptionReferenceArticle->setReferenceArticle(null);
            }
        }
        return $this;
    }

    public function addArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self {
        if(!$this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur[] = $articlesFournisseur;
            $articlesFournisseur->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self {
        if($this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur->removeElement($articlesFournisseur);
            // set the owning side to null (unless already changed)
            if($articlesFournisseur->getReferenceArticle() === $this) {
                $articlesFournisseur->setReferenceArticle(null);
            }
        }

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
     * @return Collection<int,MouvementStock>
     */
    public function getMouvements(): Collection {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self {
        if(!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setRefArticle($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getRefArticle() === $this) {
                $mouvement->setRefArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InventoryEntry>
     */
    public function getInventoryEntries(): Collection {
        return $this->inventoryEntries;
    }

    public function addInventoryEntry(InventoryEntry $inventoryEntry): self {
        if(!$this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries[] = $inventoryEntry;
            $inventoryEntry->setRefArticle($this);
        }

        return $this;
    }

    public function removeInventoryEntry(InventoryEntry $inventoryEntry): self {
        if($this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries->removeElement($inventoryEntry);
            // set the owning side to null (unless already changed)
            if($inventoryEntry->getRefArticle() === $this) {
                $inventoryEntry->setRefArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InventoryCategoryHistory>
     */
    public function getInventoryCategoryHistory(): Collection {
        return $this->inventoryCategoryHistory;
    }

    public function addInventoryCategoryHistory(InventoryCategoryHistory $inventoryCategoryHistory): self {
        if(!$this->inventoryCategoryHistory->contains($inventoryCategoryHistory)) {
            $this->inventoryCategoryHistory[] = $inventoryCategoryHistory;
            $inventoryCategoryHistory->setRefArticle($this);
        }

        return $this;
    }

    public function removeInventoryCategoryHistory(InventoryCategoryHistory $inventoryCategoryHistory): self {
        if($this->inventoryCategoryHistory->contains($inventoryCategoryHistory)) {
            $this->inventoryCategoryHistory->removeElement($inventoryCategoryHistory);
            // set the owning side to null (unless already changed)
            if($inventoryCategoryHistory->getRefArticle() === $this) {
                $inventoryCategoryHistory->setRefArticle(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?InventoryCategory {
        return $this->category;
    }

    public function setCategory(?InventoryCategory $category): self {
        $this->category = $category;

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
     * @return Collection<int, InventoryMission>
     */
    public function getInventoryMissions(): Collection {
        return $this->inventoryMissions;
    }

    public function addInventoryMission(InventoryMission $inventoryMission): self {
        if(!$this->inventoryMissions->contains($inventoryMission)) {
            $this->inventoryMissions[] = $inventoryMission;
            $inventoryMission->addRefArticle($this);
        }

        return $this;
    }

    public function removeInventoryMission(InventoryMission $inventoryMission): self {
        if($this->inventoryMissions->contains($inventoryMission)) {
            $this->inventoryMissions->removeElement($inventoryMission);
            $inventoryMission->removeRefArticle($this);
        }

        return $this;
    }

    public function getDateLastInventory(): ?DateTimeInterface {
        return $this->dateLastInventory;
    }

    public function setDateLastInventory(?DateTimeInterface $dateLastInventory): self {
        $this->dateLastInventory = $dateLastInventory;

        return $this;
    }

    public function getBarCode(): ?string {
        return $this->barCode;
    }

    public function setBarCode(?string $barCode): self {
        $this->barCode = $barCode;

        return $this;
    }

    public function getLimitSecurity() {
        return $this->limitSecurity;
    }

    public function setLimitSecurity(?int $limitSecurity): self {
        $this->limitSecurity = $limitSecurity;
        return $this;
    }

    public function getLimitWarning() {
        return $this->limitWarning;
    }

    public function setLimitWarning(?int $limitWarning): self {
        $this->limitWarning = $limitWarning;
        return $this;
    }

    /**
     * @return Collection<int,OrdreCollecteReference>
     */
    public function getOrdreCollecteReferences(): Collection {
        return $this->ordreCollecteReferences;
    }

    public function addOrdreCollecteReference(OrdreCollecteReference $ordreCollecteReference): self {
        if(!$this->ordreCollecteReferences->contains($ordreCollecteReference)) {
            $this->ordreCollecteReferences[] = $ordreCollecteReference;
            $ordreCollecteReference->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeOrdreCollecteReference(OrdreCollecteReference $ordreCollecteReference): self {
        if($this->ordreCollecteReferences->contains($ordreCollecteReference)) {
            $this->ordreCollecteReferences->removeElement($ordreCollecteReference);
            // set the owning side to null (unless already changed)
            if($ordreCollecteReference->getReferenceArticle() === $this) {
                $ordreCollecteReference->setReferenceArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PreparationOrderReferenceLine>
     */
    public function getPreparationOrderReferenceLines(): Collection {
        return $this->preparationOrderReferenceLines;
    }

    public function addPreparationOrderReferenceLine(PreparationOrderReferenceLine $line): self {
        if(!$this->preparationOrderReferenceLines->contains($line)) {
            $this->preparationOrderReferenceLines[] = $line;
            $line->setReference($this);
        }

        return $this;
    }

    public function removePreparationOrderReferenceLine(PreparationOrderReferenceLine $ligneArticlePreparation): self {
        if($this->preparationOrderReferenceLines->contains($ligneArticlePreparation)) {
            $this->preparationOrderReferenceLines->removeElement($ligneArticlePreparation);
            // set the owning side to null (unless already changed)
            if($ligneArticlePreparation->getReference() === $this) {
                $ligneArticlePreparation->setReference(null);
            }
        }

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
     * @return self
     */
    public function setTrackingPack(?Pack $pack): self {
        if(isset($this->trackingPack)
            && $this->trackingPack !== $pack) {
            $this->trackingPack->setReferenceArticle(null);
        }
        $this->trackingPack = $pack;
        if(isset($this->trackingPack)
            && $this->trackingPack->getReferenceArticle() !== $this) {
            $this->trackingPack->setReferenceArticle($this);
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function hasTrackingMovements(): bool {
        return (
            isset($this->trackingPack)
            && !($this->trackingPack->getTrackingMovements()->isEmpty())
        );
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getManagers(): Collection {
        return $this->managers;
    }

    public function addManager(Utilisateur $manager): self {
        if(!$this->managers->contains($manager)) {
            $this->managers[] = $manager;
        }

        return $this;
    }

    public function removeManager(Utilisateur $manager): self {
        if($this->managers->contains($manager)) {
            $this->managers->removeElement($manager);
        }

        return $this;
    }

    public function getNeedsMobileSync(): ?bool {
        return $this->needsMobileSync;
    }

    public function setNeedsMobileSync(?bool $needsMobileSync): self {
        $this->needsMobileSync = $needsMobileSync;

        return $this;
    }

    public function isInRequestsInProgress(): bool {
        $ligneArticles = $this->getDeliveryRequestLines();
        $inProgress = false;
        foreach($ligneArticles as $ligneArticle) {
            $demande = $ligneArticle->getRequest();
            if($demande->needsToBeProcessed()) {
                $inProgress = true;
                break;
            }
        }
        return $inProgress;
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
            $transferRequest->addReference($this);
        }

        return $this;
    }

    public function removeTransferRequest(TransferRequest $transferRequest): self {
        if($this->transferRequests->contains($transferRequest)) {
            $this->transferRequests->removeElement($transferRequest);
            $transferRequest->removeReference($this);
        }

        return $this;
    }

    public function getStockManagement(): ?string {
        return $this->stockManagement;
    }

    public function setStockManagement(?string $stockManagement): self {
        $this->stockManagement = $stockManagement;

        return $this;
    }

    /**
     * @return Collection<int, Alert>
     */
    public function getAlerts(): Collection {
        return $this->alerts;
    }

    public function addAlert(Alert $alert): self {
        if(!$this->alerts->contains($alert)) {
            $this->alerts[] = $alert;
            $alert->setReference($this);
        }

        return $this;
    }

    public function removeAlert(Alert $alert): self {
        if($this->alerts->contains($alert)) {
            $this->alerts->removeElement($alert);
            // set the owning side to null (unless already changed)
            if($alert->getReference() === $this) {
                $alert->setReference(null);
            }
        }

        return $this;
    }

    public function getBuyer(): ?Utilisateur {
        return $this->buyer;
    }

    public function setBuyer(?Utilisateur $buyer): self {
        $this->buyer = $buyer;

        return $this;
    }

    /**
     * @return Collection<int, Cart>
     */
    public function getCarts(): Collection {
        return $this->carts;
    }

    public function addCart(Cart $cart): self {
        if(!$this->carts->contains($cart)) {
            $this->carts[] = $cart;
            $cart->addReference($this);
        }

        return $this;
    }

    public function removeCart(Cart $cart): self {
        if($this->carts->removeElement($cart)) {
            $cart->removeReference($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, PurchaseRequestLine>
     */
    public function getPurchaseRequestLines(): Collection {
        return $this->purchaseRequestLines;
    }

    public function getAssociatedArticles(bool $active = false, bool $count = false): array|int {
        $articles = $this->typeQuantite === self::QUANTITY_TYPE_REFERENCE
            ? []
            : Stream::from($this->articlesFournisseur)
                ->flatMap(fn(ArticleFournisseur $articleFournisseur) => $articleFournisseur->getArticles()->toArray())
                ->unique()
                ->filter(fn( Article $article) => !$active || $article->getStatut()->getCode() === Article::STATUT_ACTIF)
                ->toArray();

        return $count ? count($articles) : $articles;
    }

    public function hasArticles(): bool {
        return Stream::from($this->articlesFournisseur)
            ->some(fn(ArticleFournisseur $articleFournisseur) => !empty($articleFournisseur->getArticles()));
    }

    public function addPurchaseRequestLine(PurchaseRequestLine $purchaseRequestLine): self {
        if(!$this->purchaseRequestLines->contains($purchaseRequestLine)) {
            $this->purchaseRequestLines[] = $purchaseRequestLine;
            $purchaseRequestLine->setReference($this);
        }

        return $this;
    }

    public function removePurchaseRequestLine(PurchaseRequestLine $purchaseRequestLine): self {
        if($this->purchaseRequestLines->removeElement($purchaseRequestLine)) {
            if($purchaseRequestLine->getReference() === $this) {
                $purchaseRequestLine->setReference(null);
            }
        }

        return $this;
    }

    public function setPurchaseRequestLines(?array $purchaseRequestLines): self {
        foreach($this->getPurchaseRequestLines()->toArray() as $purchaseRequestLine) {
            $this->removePurchaseRequestLine($purchaseRequestLine);
        }

        $this->purchaseRequestLines = new ArrayCollection();
        foreach($purchaseRequestLines as $purchaseRequestLine) {
            $this->addPurchaseRequestLine($purchaseRequestLine);
        }

        return $this;
    }

    public function getOrderState(): ?string {
        return $this->orderState;
    }

    public function setOrderState(?string $orderState): self {
        $this->orderState = $orderState;
        return $this;
    }

    /**
     * @return VisibilityGroup|null
     */
    public function getVisibilityGroup(): ?VisibilityGroup {
        return $this->visibilityGroup;
    }

    public function setVisibilityGroup(?VisibilityGroup $visibilityGroup): self {
        if($this->visibilityGroup && $this->visibilityGroup !== $visibilityGroup) {
            $this->visibilityGroup->removeArticleReference($this);
        }
        $this->visibilityGroup = $visibilityGroup;
        if($visibilityGroup) {
            $visibilityGroup->addArticleReference($this);
        }
        return $this;
    }

    public function getImage(): ?Attachment {
        return $this->image;
    }

    public function setImage(?Attachment $image): self {
        $this->image = $image;

        return $this;
    }

    public function getCreatedBy(): ?Utilisateur {
        return $this->createdBy;
    }

    public function setCreatedBy(?Utilisateur $createdBy): self {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getEditedBy(): ?Utilisateur {
        return $this->editedBy;
    }

    public function setEditedBy(?Utilisateur $editedBy): self {
        $this->editedBy = $editedBy;

        return $this;
    }

    public function getEditedAt(): ?DateTime {
        return $this->editedAt;
    }

    public function setEditedAt(?DateTime $editedAt): self {
        $this->editedAt = $editedAt;

        return $this;
    }

    public function getLastStockEntry(): ?DateTime {
        return $this->lastStockEntry;
    }

    public function setLastStockEntry(?DateTime $lastStockEntry): self {
        $this->lastStockEntry = $lastStockEntry;

        return $this;
    }

    public function getLastStockExit(): ?DateTime {
        return $this->lastStockExit;
    }

    public function setLastStockExit(?DateTime $lastStockExit): self {
        $this->lastStockExit = $lastStockExit;

        return $this;
    }

    public function hasUpToDateInventory(): ?bool {
        return $this->upToDateInventory;
    }

    public function setUpToDateInventory(?bool $upToDateInventory): self {
        $this->upToDateInventory = $upToDateInventory;

        return $this;
    }

    public function getDescription(): ?array {
        return $this->description;
    }

    public function setDescription(?array $description): self {
        $this->description = $description;

        return $this;
    }

    public function isHandledByReference(): bool {
        return $this->typeQuantite === self::QUANTITY_TYPE_REFERENCE;
    }

    /**
     * @return Collection<int, StorageRule>
     */
    public function getStorageRules(): Collection {
        return $this->storageRules;
    }

    public function addStorageRule(StorageRule $storageRule): self {
        if (!$this->storageRules->contains($storageRule)) {
            $this->storageRules[] = $storageRule;
            $storageRule->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeStorageRule(StorageRule $storageRule): self {
        if ($this->storageRules->removeElement($storageRule)) {
            if ($storageRule->getReferenceArticle() === $this) {
                $storageRule->setReferenceArticle(null);
            }
        }

        return $this;
    }

    public function setStorageRules(?iterable $storageRules): self {
        foreach($this->getStorageRules()->toArray() as $storageRule) {
            $this->removeStorageRule($storageRule);
        }

        $this->storageRules = new ArrayCollection();
        foreach($storageRules ?? [] as $storageRule) {
            $this->addStorageRule($storageRule);
        }

        return $this;
    }

    public function getNdpCode(): ?string {
        return $this->ndpCode;
    }

    public function setNdpCode(?string $ndpCode): self {
        $this->ndpCode = $ndpCode;

        return $this;
    }

    public function getOnuCode(): ?string {
        return $this->onuCode;
    }

    public function setOnuCode(?string $onuCode): self {
        $this->onuCode = $onuCode;

        return $this;
    }

    public function getProductClass(): ?string {
        return $this->productClass;
    }

    public function setProductClass(?string $productClass): self {
        $this->productClass = $productClass;

        return $this;
    }

    public function isDangerousGoods(): ?bool {
        return $this->dangerousGoods;
    }

    public function setDangerousGoods(bool $dangerousGoods): self {
        $this->dangerousGoods = $dangerousGoods;

        return $this;
    }

    public function getSheet(): ?Attachment {
        return $this->sheet;
    }
    public function setSheet(?Attachment $sheet): self {
        $this->sheet = $sheet;

        return $this;
    }

    public function getLastSleepingStockAlertAnswer(): ?DateTimeInterface {
        return $this->lastSleepingStockAlertAnswer;
    }

    /**
     * @param DateTimeInterface $lastSleepingStockAlertAnswer cannot be null, because if a user has responded once, it cannot be reset to 'never responded'.
     */
    public function setLastSleepingStockAlertAnswer(DateTimeInterface $lastSleepingStockAlertAnswer): self {
        $this->lastSleepingStockAlertAnswer = $lastSleepingStockAlertAnswer;

        return $this;
    }
}
