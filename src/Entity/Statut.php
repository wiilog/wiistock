<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\RequestTemplate\HandlingRequestTemplate;
use App\Entity\Type\Type;
use App\Exceptions\FormException;
use App\Helper\LanguageHelper;
use App\Repository\StatutRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatutRepository::class)]
class Statut {

    const DRAFT = 0;
    const NOT_TREATED = 1;
    const IN_PROGRESS = 2;// 5
    const PARTIAL = 3; // 4
    const TREATED = 4; // 2
    const DISPUTE = 5; // 3

    const SCHEDULED = 6;
    const SHIPPED = 7;

    const GROUPED_SIGNATURE_DEFAULT_COLOR = '#3353D7';

    const REGEX_STATUS_NAME_VALIDATE  = '/^[^,;]*$/';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $code = null;

    /**
     * Attribute used for data warehouse, do not delete it
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $displayOrder = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $state = null;

    #[ORM\ManyToOne(targetEntity: CategorieStatut::class)]
    private ?CategorieStatut $categorie = null;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Article::class)]
    private Collection $articles;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Reception::class)]
    private Collection $receptions;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Demande::class)]
    private Collection $demandes;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Preparation::class)]
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

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $sendNotifToBuyer = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $commentNeeded = null;

    #[ORM\OneToMany(mappedBy: 'statut', targetEntity: Arrivage::class)]
    private Collection $arrivages;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: TransferRequest::class)]
    private Collection $transferRequests;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: TransferOrder::class)]
    private Collection $transferOrders;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $sendNotifToDeclarant = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $sendReport = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $overconsumptionBillGenerationStatus = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'statuts')]
    private ?Type $type = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $sendNotifToRecipient = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $needsMobileSync = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $automaticReceptionCreation = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private ?bool $defaultForCategory = false;

    #[ORM\OneToMany(mappedBy: 'status', targetEntity: PurchaseRequest::class)]
    private Collection $purchaseRequests;

    #[ORM\OneToMany(mappedBy: 'requestStatus', targetEntity: HandlingRequestTemplate::class)]
    private Collection $handlingRequestStatusTemplates;

    #[ORM\OneToOne(mappedBy: "status", targetEntity: TranslationSource::class)]
    private ?TranslationSource $labelTranslation = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $groupedSignatureType = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['default' => Statut::GROUPED_SIGNATURE_DEFAULT_COLOR])]
    private ?string $groupedSignatureColor = Statut::GROUPED_SIGNATURE_DEFAULT_COLOR;

    #[ORM\Column(type: Types::STRING, length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $displayedOnSchedule = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private ?bool $createDropMovementOnDropLocation = false;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Type $typeForGeneratedDispatchOnStatusChange = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    #[ORM\JoinTable("status_notification_user")]
    #[ORM\JoinColumn(name: "status_id", referencedColumnName: "id")]
    #[ORM\InverseJoinColumn(name: "user_id", referencedColumnName: "id")]
    private Collection $notifiedUsers;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $requiredAttachment = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $preventStatusChangeWithoutDeliveryFees = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $passStatusAtPurchaseOrderGeneration = false;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'authorizedRequestStatuses')]
    #[ORM\JoinTable(name: 'authorized_request_creation_status_role')]
    private Collection $authorizedRequestCreationRoles;

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
        $this->notifiedUsers = new ArrayCollection();
        $this->authorizedRequestCreationRoles = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabelIn(Language|string $in, Language|string|null $default = null): ?string {
        $in = LanguageHelper::clearLanguage($in);
        $default = LanguageHelper::clearLanguage($default);

        $translation = $this->getLabelTranslation();

        return $translation?->getTranslationIn($in, $default)?->getTranslation()
            ?: $translation?->getTranslationIn( Language::FRENCH_SLUG)?->getTranslation()
            ?: '';
    }

    public function getNom(): ?string {
        return $this->nom;
    }

    public function setNom(?string $nom): self {
        if (!preg_match(self::REGEX_STATUS_NAME_VALIDATE, $nom)) {
            throw new FormException('Le libellé d\'un statut ne doit pas contenir de virgule ou de point-virgule');
        }
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

    public function isScheduled(): ?bool {
        return $this->state === self::SCHEDULED;
    }

    public function isShipped(): ?bool {
        return $this->state === self::SHIPPED;
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
            $dispatch->setStatus($this);
        }

        return $this;
    }

    public function removeDispatch(Dispatch $dispatch): self {
        if($this->dispatches->contains($dispatch)) {
            $this->dispatches->removeElement($dispatch);
            // set the owning side to null (unless already changed)
            if($dispatch->getStatut() === $this) {
                $dispatch->setStatus(null);
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
        return $this->code ?? $this->getSlug();
    }

    public function getSlug(): ?string {
        $name = $this->getNom();
        $search  = ['À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'à', 'á', 'â', 'ã', 'ä', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'];
        $replace = ['A', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y'];
        $name = str_replace($search, $replace, $name);
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
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

    public function getSendReport(): ?bool {
        return $this->sendReport;
    }

    public function setSendReport(?bool $sendReport): self {
        $this->sendReport = $sendReport;

        return $this;
    }

    public function getOverconsumptionBillGenerationStatus(): ?bool {
        return $this->overconsumptionBillGenerationStatus;
    }

    public function setOverconsumptionBillGenerationStatus(?bool $overconsumptionBillGenerationStatus): self {
        $this->overconsumptionBillGenerationStatus = $overconsumptionBillGenerationStatus;

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

    public function getLabelTranslation(): ?TranslationSource {
        return $this->labelTranslation;
    }

    public function setLabelTranslation(?TranslationSource $labelTranslation): self {
        if($this->labelTranslation && $this->labelTranslation->getStatus() !== $this) {
            $oldLabelTranslation = $this->labelTranslation;
            $this->labelTranslation = null;
            $oldLabelTranslation->setStatus(null);
        }
        $this->labelTranslation = $labelTranslation;
        if($this->labelTranslation && $this->labelTranslation->getType() !== $this) {
            $this->labelTranslation->setStatus($this);
        }

        return $this;
    }

    public function getGroupedSignatureType(): ?string
    {
        return $this->groupedSignatureType;
    }

    public function setGroupedSignatureType(?string $groupedSignatureType): self
    {
        $this->groupedSignatureType = $groupedSignatureType;

        return $this;
    }

    public function getGroupedSignatureColor(): ?string
    {
        return $this->groupedSignatureColor;
    }

    public function setGroupedSignatureColor(?string $groupedSignatureColor): self
    {
        $this->groupedSignatureColor = $groupedSignatureColor;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function getTypeForGeneratedDispatchOnStatusChange(): ?Type
    {
        return $this->typeForGeneratedDispatchOnStatusChange;
    }

    public function setTypeForGeneratedDispatchOnStatusChange(?Type $typeForGeneratedDispatchOnStatusChange): self {
        $this->typeForGeneratedDispatchOnStatusChange = $typeForGeneratedDispatchOnStatusChange;

        return $this;
    }

    public function getNotifiedUsers(): Collection {
        return $this->notifiedUsers;
    }

    public function addNotifiedUser(Utilisateur $notifiedUser): self {
        if(!$this->notifiedUsers->contains($notifiedUser)) {
            $this->notifiedUsers[] = $notifiedUser;
        }

        return $this;
    }

    public function removeNotifiedUser(Utilisateur $notifiedUser): self {
        if($this->notifiedUsers->contains($notifiedUser)) {
            $this->notifiedUsers->removeElement($notifiedUser);
        }

        return $this;
    }

    public function setNotifiedUsers(?iterable $notifiedUsers): self {
        foreach($this->getNotifiedUsers()->toArray() as $notifiedUser) {
            $this->removeNotifiedUser($notifiedUser);
        }

        $this->notifiedUsers = new ArrayCollection();
        foreach($notifiedUsers ?? [] as $notifiedUser) {
            $this->addNotifiedUser($notifiedUser);
        }

        return $this;
    }

    public function isDisplayedOnSchedule(): ?bool {
        return $this->displayedOnSchedule;
    }

    public function setDisplayOnSchedule(?bool $displayedOnSchedule): self {
        $this->displayedOnSchedule = $displayedOnSchedule;

        return $this;
    }

    public function isCreateDropMovementOnDropLocation(): ?bool {
        return $this->createDropMovementOnDropLocation;
    }

    public function setCreateDropMovementOnDropLocation(bool $createDropMovementOnDropLocation): self {
        $this->createDropMovementOnDropLocation = $createDropMovementOnDropLocation;

        return $this;
    }

    public function isRequiredAttachment(): ?bool {
        return $this->requiredAttachment;
    }

    public function setRequiredAttachment(?bool $requiredAttachment): self {
        $this->requiredAttachment = $requiredAttachment;

        return $this;
    }

    public function isPreventStatusChangeWithoutDeliveryFees(): ?bool {
        return $this->preventStatusChangeWithoutDeliveryFees;
    }

    public function setPreventStatusChangeWithoutDeliveryFees(?bool $preventStatusChangeWithoutDeliveryFees): self {
        $this->preventStatusChangeWithoutDeliveryFees = $preventStatusChangeWithoutDeliveryFees;

        return $this;
    }

    public function isPassStatusAtPurchaseOrderGeneration(): bool
    {
        return $this->passStatusAtPurchaseOrderGeneration;
    }

    public function setPassStatusAtPurchaseOrderGeneration(bool $passStatusAtPurchaseOrderGeneration): self
    {
        $this->passStatusAtPurchaseOrderGeneration = $passStatusAtPurchaseOrderGeneration;
        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getAuthorizedRequestCreationRoles(): Collection
    {
        return $this->authorizedRequestCreationRoles;
    }

    public function addAuthorizedRequestCreationRole(Role $role): self
    {
        if (!$this->authorizedRequestCreationRoles->contains($role)) {
            $this->authorizedRequestCreationRoles[] = $role;

            $role->addAuthorizedDispatchStatus($this);
        }

        return $this;
    }

    public function removeAuthorizedRequestCreationRole(Role $role): self
    {
        if ($this->authorizedRequestCreationRoles->removeElement($role)) {
            $role->removeAuthorizedRequestStatus($this);
        }
        return $this;
    }

    public function setAuthorizedRequestCreationRoles(?iterable $roles): self {
        foreach($this->getAuthorizedRequestCreationRoles()->toArray() as $role) {
            $this->removeAuthorizedRequestCreationRole($role);
        }

        $this->authorizedRequestCreationRoles = new ArrayCollection();
        foreach($roles ?? [] as $role) {
            $this->addAuthorizedRequestCreationRole($role);
        }

        return $this;
    }

}
