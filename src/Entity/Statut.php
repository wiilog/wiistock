<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Transport\StatusHistory;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\StatutRepository')]
class Statut {

    const DRAFT = 0;
    const NOT_TREATED = 1;
    const TREATED = 2;
    const DISPUTE = 3;
    const PARTIAL = 4;
    const IN_PROGRESS = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $displayOrder = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $state = null;

    #[ORM\ManyToOne(targetEntity: CategorieStatut::class, inversedBy: 'statuts')]
    private ?CategorieStatut $categorie = null;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Article::class)]
    private Collection $articles;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Reception::class)]
    private Collection $receptions;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: 'App\Entity\DeliveryRequest\Demande')]
    private Collection $demandes;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: 'App\Entity\PreparationOrder\Preparation')]
    private Collection $preparations;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Livraison::class)]
    private Collection $livraisons;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Collecte::class)]
    private Collection $collectes;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: ReferenceArticle::class)]
    private Collection $referenceArticles;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: Handling::class)]
    private Collection $handlings;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Dispatch::class)]
    private Collection $dispatches;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: Dispute::class)]
    private Collection $disputes;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $sendNotifToBuyer = null;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => true])]
    private ?bool $commentNeeded = null;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Arrivage::class)]
    private Collection $arrivages;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: TransferRequest::class)]
    private Collection $transferRequests;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: TransferOrder::class)]
    private Collection $transferOrders;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $sendNotifToDeclarant = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'statuts')]
    private ?Type $type = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $sendNotifToRecipient = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $needsMobileSync = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $automaticReceptionCreation = null;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private ?bool $defaultForCategory = false;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: PurchaseRequest::class)]
    private Collection $purchaseRequests;

    #[ORM\OneToMany(mappedBy: 'requestStatus', targetEntity: HandlingRequestTemplate::class)]
    private Collection $handlingRequestStatusTemplates;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: TransportRequest::class)]
    private Collection $transportRequests;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: TransportRound::class)]
    private Collection $transportRounds;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: TransportOrder::class)]
    private Collection $transportOrders;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: StatusHistory::class)]
    private Collection $transportStatusHistories;

    public function __construct() {
        $this->articles = new ArrayCollection();
        $this->receptions = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->preparations = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->handlings = new ArrayCollection();
        $this->disputes = new ArrayCollection();
        $this->dispatches = new ArrayCollection();
        $this->arrivages = new ArrayCollection();
        $this->transferRequests = new ArrayCollection();
        $this->transferOrders = new ArrayCollection();

        $this->purchaseRequests = new ArrayCollection();
        $this->handlingRequestStatusTemplates = new ArrayCollection();
        $this->transportRequests = new ArrayCollection();
        $this->transportRounds = new ArrayCollection();
        $this->transportOrders = new ArrayCollection();
        $this->transportStatusHistories = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNom(): ?string {
        return $this->nom;
    }

    public function setNom(?string $nom): self {
        $this->nom = $nom;

        return $this;
    }

    public function isTreated(): ?bool {
        return $this->state === self::TREATED;
    }

    public function isPartial(): ?bool {
        return $this->state === self::PARTIAL;
    }

    public function isDraft(): ?bool {
        return $this->state === self::DRAFT;
    }

    public function isNotTreated(): ?bool {
        return $this->state === self::NOT_TREATED;
    }

    public function isInProgress(): ?bool {
        return $this->state === self::IN_PROGRESS;
    }

    public function isDispute(): ?bool {
        return $this->state === self::DISPUTE;
    }

    public function getCategorie(): ?CategorieStatut {
        return $this->categorie;
    }

    public function setCategorie(?CategorieStatut $categorie): self {
        $this->categorie = $categorie;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection {
        return $this->articles;
    }

    public function addArticle(Article $article): self {
        if(!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setStatut($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        if($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if($article->getStatut() === $this) {
                $article->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions(): Collection {
        return $this->receptions;
    }

    public function addReception(Reception $reception): self {
        if(!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setStatut($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self {
        if($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if($reception->getStatut() === $this) {
                $reception->setStatut(null);
            }
        }

        return $this;
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
        }

        return $this;
    }

    public function removeTransferRequest(TransferRequest $transferRequest): self {
        if($this->transferRequests->contains($transferRequest)) {
            $this->transferRequests->removeElement($transferRequest);
        }

        return $this;
    }

    /**
     * @return Collection|TransferOrder[]
     */
    public function getTransferOrders(): Collection {
        return $this->transferOrders;
    }

    public function addTransferOrder(TransferOrder $transferOrder): self {
        if(!$this->transferOrders->contains($transferOrder)) {
            $this->transferOrders[] = $transferOrder;
        }

        return $this;
    }

    public function removeTransferOrder(TransferOrder $transferOrder): self {
        if($this->transferOrders->contains($transferOrder)) {
            $this->transferOrders->removeElement($transferOrder);
        }

        return $this;
    }

    /**
     * @return Collection|Demande[]
     */
    public function getDemandes(): Collection {
        return $this->demandes;
    }

    public function addDemande(Demande $demande): self {
        if(!$this->demandes->contains($demande)) {
            $this->demandes[] = $demande;
            $demande->setStatut($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self {
        if($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if($demande->getStatut() === $this) {
                $demande->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations(): Collection {
        return $this->preparations;
    }

    public function addPreparation(Preparation $preparation): self {
        if(!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setStatut($this);
        }

        return $this;
    }

    public function removePreparation(Preparation $preparation): self {
        if($this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if($preparation->getStatut() === $this) {
                $preparation->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Livraison[]
     */
    public function getLivraisons(): Collection {
        return $this->livraisons;
    }

    public function addLivraison(Livraison $livraison): self {
        if(!$this->livraisons->contains($livraison)) {
            $this->livraisons[] = $livraison;
            $livraison->setStatut($this);
        }

        return $this;
    }

    public function removeLivraison(Livraison $livraison): self {
        if($this->livraisons->contains($livraison)) {
            $this->livraisons->removeElement($livraison);
            // set the owning side to null (unless already changed)
            if($livraison->getStatut() === $this) {
                $livraison->setStatut(null);
            }
        }

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
            $collecte->setStatut($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self {
        if($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if($collecte->getStatut() === $this) {
                $collecte->setStatut(null);
            }
        }

        return $this;
    }

    public function __toString() {
        return $this->nom;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferenceArticles(): Collection {
        return $this->referenceArticles;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): self {
        if(!$this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles[] = $referenceArticle;
            $referenceArticle->setStatut($this);
        }
        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self {
        if($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if($referenceArticle->getStatut() === $this) {
                $referenceArticle->setStatut(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|Handling[]
     */
    public function getHandlings(): Collection {
        return $this->handlings;
    }

    public function addHandling(Handling $handling): self {
        if(!$this->handlings->contains($handling)) {
            $this->handlings[] = $handling;
            $handling->setStatus($this);
        }

        return $this;
    }

    public function removeHandling(Handling $handling): self {
        if($this->handlings->contains($handling)) {
            $this->handlings->removeElement($handling);
            // set the owning side to null (unless already changed)
            if($handling->getStatus() === $this) {
                $handling->setStatus(null);
            }
        }

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
            $dispute->setStatus($this);
        }

        return $this;
    }

    public function removeDispute(Dispute $dispute): self {
        if($this->disputes->contains($dispute)) {
            $this->disputes->removeElement($dispute);
            // set the owning side to null (unless already changed)
            if($dispute->getStatus() === $this) {
                $dispute->setStatus(null);
            }
        }

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getDisplayOrder(): ?int {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getDispatches(): Collection {
        return $this->dispatches;
    }

    public function addDispatch(Dispatch $dispatch): self {
        if(!$this->dispatches->contains($dispatch)) {
            $this->dispatches[] = $dispatch;
            $dispatch->setStatut($this);
        }

        return $this;
    }

    public function removeDispatch(Dispatch $dispatch): self {
        if($this->dispatches->contains($dispatch)) {
            $this->dispatches->removeElement($dispatch);
            // set the owning side to null (unless already changed)
            if($dispatch->getStatut() === $this) {
                $dispatch->setStatut(null);
            }
        }

        return $this;
    }

    public function getSendNotifToBuyer(): ?bool {
        return $this->sendNotifToBuyer;
    }

    public function setSendNotifToBuyer(?bool $sendNotifToBuyer): self {
        $this->sendNotifToBuyer = $sendNotifToBuyer;

        return $this;
    }

    public function getCommentNeeded(): ?bool {
        return $this->commentNeeded;
    }

    public function setCommentNeeded(?bool $commentNeeded): self {
        $this->commentNeeded = $commentNeeded;

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivages(): Collection {
        return $this->arrivages;
    }

    public function addArrivage(Arrivage $arrivage): self {
        if(!$this->arrivages->contains($arrivage)) {
            $this->arrivages[] = $arrivage;
            $arrivage->setStatut($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self {
        if($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if($arrivage->getStatut() === $this) {
                $arrivage->setStatut(null);
            }
        }

        return $this;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(?string $code): self {
        $this->code = $code;

        return $this;
    }

    public function getSendNotifToDeclarant(): ?bool {
        return $this->sendNotifToDeclarant;
    }

    public function setSendNotifToDeclarant(?bool $sendNotifToDeclarant): self {
        $this->sendNotifToDeclarant = $sendNotifToDeclarant;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getSendNotifToRecipient(): ?bool {
        return $this->sendNotifToRecipient;
    }

    public function setSendNotifToRecipient(?bool $sendNotifToRecipient): self {
        $this->sendNotifToRecipient = $sendNotifToRecipient;

        return $this;
    }

    public function getNeedsMobileSync(): ?bool {
        return $this->needsMobileSync;
    }

    public function setNeedsMobileSync(?bool $needsMobileSync): self {
        $this->needsMobileSync = $needsMobileSync;

        return $this;
    }

    public function getAutomaticReceptionCreation(): ?bool {
        return $this->automaticReceptionCreation;
    }

    public function setAutomaticReceptionCreation(?bool $automaticReceptionCreation): self {
        $this->automaticReceptionCreation = $automaticReceptionCreation;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDefaultForCategory(): bool {
        return $this->defaultForCategory;
    }

    /**
     * @param bool $defaultForCategory
     * @return self
     */
    public function setDefaultForCategory(bool $defaultForCategory): self {
        $this->defaultForCategory = $defaultForCategory;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getState(): ?int {
        return $this->state;
    }

    /**
     * @param mixed $state
     * @return self
     */
    public function setState(?int $state): self {
        $this->state = $state;
        return $this;
    }

    /**
     * @return Collection|PurchaseRequest[]
     */
    public function getPurchaseRequests(): Collection {
        return $this->purchaseRequests;
    }

    public function addPurchaseRequest(PurchaseRequest $purchaseRequest): self {
        if(!$this->purchaseRequests->contains($purchaseRequest)) {
            $this->purchaseRequests[] = $purchaseRequest;
            $purchaseRequest->setStatus($this);
        }

        return $this;
    }

    public function removePurchaseRequest(PurchaseRequest $purchaseRequest): self {
        if($this->purchaseRequests->removeElement($purchaseRequest)) {
            // set the owning side to null (unless already changed)
            if($purchaseRequest->getStatus() === $this) {
                $purchaseRequest->setStatus(null);
            }
        }

        return $this;
    }

    public function setPurchaseRequest(?array $purchaseRequests): self {
        foreach($this->getPurchaseRequests()->toArray() as $purchaseRequest) {
            $this->removePurchaseRequest($purchaseRequest);
        }

        $this->purchaseRequests = new ArrayCollection();
        foreach($purchaseRequests as $purchaseRequest) {
            $this->addPurchaseRequest($purchaseRequest);
        }

        return $this;
    }

    /**
     * @return Collection|HandlingRequestTemplate[]
     */
    public function getHandlingRequestStatusTemplates(): Collection {
        return $this->handlingRequestStatusTemplates;
    }

    public function addHandlingRequestStatusTemplate(HandlingRequestTemplate $handlingRequestStatusTemplate): self {
        if(!$this->handlingRequestStatusTemplates->contains($handlingRequestStatusTemplate)) {
            $this->handlingRequestStatusTemplates[] = $handlingRequestStatusTemplate;
            $handlingRequestStatusTemplate->setRequestStatus($this);
        }

        return $this;
    }

    public function removeHandlingRequestStatusTemplate(HandlingRequestTemplate $handlingRequestStatusTemplate): self {
        if($this->handlingRequestStatusTemplates->removeElement($handlingRequestStatusTemplate)) {
            // set the owning side to null (unless already changed)
            if($handlingRequestStatusTemplate->getRequestStatus() === $this) {
                $handlingRequestStatusTemplate->setRequestStatus(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportRequest>
     */
    public function getTransportRequests(): Collection
    {
        return $this->transportRequests;
    }

    public function addTransportRequest(TransportRequest $transportRequest): self
    {
        if (!$this->transportRequests->contains($transportRequest)) {
            $this->transportRequests[] = $transportRequest;
            $transportRequest->setStatus($this);
        }

        return $this;
    }

    public function removeTransportRequest(TransportRequest $transportRequest): self
    {
        if ($this->transportRequests->removeElement($transportRequest)) {
            // set the owning side to null (unless already changed)
            if ($transportRequest->getStatus() === $this) {
                $transportRequest->setStatus(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportRound>
     */
    public function getTransportRounds(): Collection
    {
        return $this->transportRounds;
    }

    public function addTransportRound(TransportRound $transportRound): self
    {
        if (!$this->transportRounds->contains($transportRound)) {
            $this->transportRounds[] = $transportRound;
            $transportRound->setStatus($this);
        }

        return $this;
    }

    public function removeTransportRound(TransportRound $transportRound): self
    {
        if ($this->transportRounds->removeElement($transportRound)) {
            // set the owning side to null (unless already changed)
            if ($transportRound->getStatus() === $this) {
                $transportRound->setStatus(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TransportOrder>
     */
    public function getTransportOrders(): Collection
    {
        return $this->transportOrders;
    }

    public function addTransportOrder(TransportOrder $transportOrder): self
    {
        if (!$this->transportOrders->contains($transportOrder)) {
            $this->transportOrders[] = $transportOrder;
            $transportOrder->setStatus($this);
        }

        return $this;
    }

    public function removeTransportOrder(TransportOrder $transportOrder): self
    {
        if ($this->transportOrders->removeElement($transportOrder)) {
            // set the owning side to null (unless already changed)
            if ($transportOrder->getStatus() === $this) {
                $transportOrder->setStatus(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StatusHistory>
     */
    public function getTransportStatusHistories(): Collection
    {
        return $this->transportStatusHistories;
    }

    public function addTransportStatusHistory(StatusHistory $transportStatusHistory): self
    {
        if (!$this->transportStatusHistories->contains($transportStatusHistory)) {
            $this->transportStatusHistories[] = $transportStatusHistory;
            $transportStatusHistory->setStatus($this);
        }

        return $this;
    }

    public function removeTransportStatusHistory(StatusHistory $transportStatusHistory): self
    {
        if ($this->transportStatusHistories->removeElement($transportStatusHistory)) {
            // set the owning side to null (unless already changed)
            if ($transportStatusHistory->getStatus() === $this) {
                $transportStatusHistory->setStatus(null);
            }
        }

        return $this;
    }

}
