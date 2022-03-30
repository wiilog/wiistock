<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\IOT\RequestTemplateLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Traits\CommentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Entity\Traits\LitePropertiesSetterTrait;
use App\Repository\ReferenceArticleRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: ReferenceArticleRepository::class)]
class ReferenceArticle {

    use FreeFieldsManagerTrait;
    use AttachmentTrait;
    use CommentTrait;
    use LitePropertiesSetterTrait;

    const CATEGORIE = 'referenceArticle';
    const STATUT_ACTIF = 'actif';
    const STATUT_INACTIF = 'inactif';
    const DRAFT_STATUS = 'brouillon';
    const QUANTITY_TYPE_REFERENCE = 'reference';
    const QUANTITY_TYPE_ARTICLE = 'article';
    const BARCODE_PREFIX = 'REF';
    const STOCK_MANAGEMENT_FEFO = 'FEFO';
    const STOCK_MANAGEMENT_FIFO = 'FIFO';
    const PURCHASE_IN_PROGRESS_ORDER_STATE = "purchaseInProgress";
    const WAIT_FOR_RECEPTION_ORDER_STATE = "waitForReception";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'string', length: 15, nullable: true)]
    private ?string $barCode = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantiteDisponible = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantiteReservee = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantiteStock = null;

    #[ORM\OneToMany(targetEntity: DeliveryRequestReferenceLine::class, mappedBy: 'reference')]
    private Collection $deliveryRequestLines;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'referenceArticles')]
    private ?Type $type = null;

    #[ORM\OneToMany(targetEntity: ArticleFournisseur::class, mappedBy: 'referenceArticle')]
    private Collection $articlesFournisseur;

    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    private ?string $typeQuantite = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'referenceArticles')]
    private ?Statut $statut = null;

    #[ORM\OneToMany(targetEntity: CollecteReference::class, mappedBy: 'referenceArticle')]
    private Collection $collecteReferences;

    #[ORM\OneToMany(targetEntity: OrdreCollecteReference::class, mappedBy: 'referenceArticle')]
    private Collection $ordreCollecteReferences;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\OneToMany(targetEntity: ReceptionReferenceArticle::class, mappedBy: 'referenceArticle')]
    private Collection $receptionReferenceArticles;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'referenceArticles')]
    private ?Emplacement $emplacement = null;

    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'refArticle')]
    private Collection $mouvements;

    #[ORM\ManyToOne(targetEntity: InventoryCategory::class, inversedBy: 'refArticle')]
    private ?InventoryCategory $category = null;

    #[ORM\OneToMany(targetEntity: InventoryEntry::class, mappedBy: 'refArticle')]
    private Collection $inventoryEntries;

    #[ORM\OneToMany(targetEntity: InventoryCategoryHistory::class, mappedBy: 'refArticle')]
    private Collection $inventoryCategoryHistory;

    #[ORM\ManyToMany(targetEntity: InventoryMission::class, mappedBy: 'refArticles')]
    private Collection $inventoryMissions;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $prixUnitaire = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $dateLastInventory = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $limitSecurity = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $limitWarning = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isUrgent = false;

    #[ORM\OneToMany(targetEntity: PreparationOrderReferenceLine::class, mappedBy: 'reference')]
    private Collection $preparationOrderReferenceLines;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $emergencyComment = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'referencesEmergenciesTriggered')]
    private ?Utilisateur $userThatTriggeredEmergency = null;

    /**
     * @var Pack|null
     */
    #[ORM\OneToOne(targetEntity: Pack::class, mappedBy: 'referenceArticle')]
    private ?Pack $trackingPack = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $needsMobileSync = null;

    #[ORM\ManyToMany(targetEntity: TransferRequest::class, mappedBy: 'references')]
    private Collection $transferRequests;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $stockManagement = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'referencesArticle')]
    private Collection $managers;

    #[ORM\OneToMany(targetEntity: Alert::class, mappedBy: 'reference', cascade: ['remove'])]
    private Collection $alerts;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'referencesBuyer')]
    private ?Utilisateur $buyer = null;

    #[ORM\ManyToMany(targetEntity: Cart::class, mappedBy: 'references')]
    private ?Collection $carts;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $orderState = null;

    #[ORM\OneToMany(targetEntity: PurchaseRequestLine::class, mappedBy: 'reference')]
    private ?Collection $purchaseRequestLines;

    #[ORM\OneToMany(targetEntity: RequestTemplateLine::class, mappedBy: 'reference', orphanRemoval: true)]
    private Collection $requestTemplateLines;

    #[ORM\ManyToOne(targetEntity: VisibilityGroup::class, inversedBy: 'articleReferences')]
    private ?VisibilityGroup $visibilityGroup = null;

    #[ORM\OneToOne(targetEntity: Attachment::class, inversedBy: 'referenceArticle', cascade: ['persist', 'remove'])]
    private ?Attachment $image = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'createdByReferenceArticles')]
    private ?Utilisateur $createdBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'editedByReferenceArticles')]
    private ?Utilisateur $editedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $editedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $lastStockEntry = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $lastStockExit = null;

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
        $this->requestTemplateLines = new ArrayCollection();

        $this->quantiteStock = 0;
        $this->quantiteReservee = 0;
        $this->quantiteDisponible = 0;
    }

    public function getId() {
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
     * @return Collection|DeliveryRequestReferenceLine[]
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
     * @return Collection|ArticleFournisseur[]
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
     * @return Collection|CollecteReference[]
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
     * @return Collection|ReceptionReferenceArticle[]
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
     * @return Collection|MouvementStock[]
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
     * @return Collection|InventoryEntry[]
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
     * @return Collection|InventoryCategoryHistory[]
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
     * @return Collection|InventoryMission[]
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
     * @return Collection|OrdreCollecteReference[]
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

    public function getIsUrgent(): ?bool {
        return $this->isUrgent;
    }

    public function setIsUrgent(?bool $isUrgent): self {
        $this->isUrgent = $isUrgent;

        return $this;
    }

    /**
     * @return Collection|PreparationOrderReferenceLine[]
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

    public function getEmergencyComment(): ?string {
        return $this->emergencyComment;
    }

    public function setEmergencyComment(?string $emergencyComment): self {
        $this->emergencyComment = $emergencyComment;

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
     * @return Collection|Utilisateur[]
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

    public function getUserThatTriggeredEmergency(): ?Utilisateur {
        return $this->userThatTriggeredEmergency;
    }

    public function setUserThatTriggeredEmergency(?Utilisateur $userThatTriggeredEmergency): self {
        $this->userThatTriggeredEmergency = $userThatTriggeredEmergency;

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
     * @return Collection|Alert[]
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
     * @return Collection|Cart[]
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

    public function getPurchaseRequestLines(): ?Collection {
        return $this->purchaseRequestLines;
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

    public function getAssociatedArticles(): array {
        return $this->typeQuantite === self::QUANTITY_TYPE_REFERENCE
            ? []
            : Stream::from($this->articlesFournisseur)
                ->map(function(ArticleFournisseur $articleFournisseur) {
                    return $articleFournisseur->getArticles()->toArray();
                })
                ->flatten()
                ->unique()
                ->toArray();
    }

    public function getOrderState(): ?string {
        return $this->orderState;
    }

    public function setOrderState(?string $orderState): self {
        $this->orderState = $orderState;
        return $this;
    }

    /**
     * @return Collection|RequestTemplateLine[]
     */
    public function getRequestTemplateLines(): Collection {
        return $this->requestTemplateLines;
    }

    public function addRequestTemplateLine(RequestTemplateLine $requestTemplateLine): self {
        if(!$this->requestTemplateLines->contains($requestTemplateLine)) {
            $this->requestTemplateLines[] = $requestTemplateLine;
            $requestTemplateLine->setReference($this);
        }

        return $this;
    }

    public function removeRequestTemplateLine(RequestTemplateLine $requestTemplateLine): self {
        if($this->requestTemplateLines->removeElement($requestTemplateLine)) {
            // set the owning side to null (unless already changed)
            if($requestTemplateLine->getReference() === $this) {
                $requestTemplateLine->setReference(null);
            }
        }

        return $this;
    }

    /**
     * @return VisibilityGroup
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
        if($this->image && $this->image->getReferenceArticle() !== $this) {
            $oldImage = $this->image;
            $this->image = null;
            $oldImage->setReferenceArticle(null);
        }
        $this->image = $image;
        if($this->image && $this->image->getReferenceArticle() !== $this) {
            $this->image->setReferenceArticle($this);
        }

        return $this;
    }

    public function getCreatedBy(): ?Utilisateur {
        return $this->createdBy;
    }

    public function setCreatedBy(?Utilisateur $createdBy): self {
        if($this->createdBy && $this->createdBy !== $createdBy) {
            $this->createdBy->removeCreatedByReferenceArticle($this);
        }
        $this->createdBy = $createdBy;
        if($createdBy) {
            $createdBy->addCreatedByReferenceArticle($this);
        }

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
        if($this->editedBy && $this->editedBy !== $editedBy) {
            $this->editedBy->removeEditedByReferenceArticle($this);
        }
        $this->editedBy = $editedBy;
        if($editedBy) {
            $editedBy->addEditedByReferenceArticle($this);
        }

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

}
