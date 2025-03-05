<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\FreeField\FreeFieldManagementRule;
use App\Entity\IOT\Sensor;
use App\Entity\RequestTemplate\RequestTemplate;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Helper\LanguageHelper;
use App\Repository\TypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeRepository::class)]
class Type {

    // type de la catégorie réception
    const LABEL_RECEPTION = 'RECEPTION';
    // types de la catégorie litige
    // Safran Ceramics
    const LABEL_MANQUE_BL = 'manque BL';
    const LABEL_MANQUE_INFO_BL = 'manque info BL';
    const LABEL_ECART_QTE = 'écart quantité + ou -';
    const LABEL_ECART_QUALITE = 'écart qualité';
    const LABEL_PB_COMMANDE = 'problème de commande';
    const LABEL_DEST_NON_IDENT = 'destinataire non identifiable';
    // types de la catégorie demande de livraison
    const LABEL_STANDARD = 'standard';
    // types de la catégorie mouvement traça
    const LABEL_MVT_TRACA = 'MOUVEMENT TRACA';
    const LABEL_HANDLING = 'service';
    const LABEL_SENSOR = 'capteur';
    const LABEL_DELIVERY = 'livraison';
    const LABEL_COLLECT = 'collecte';
    const LABEL_SCHEDULED_EXPORT = 'Export planifié';
    const LABEL_UNIQUE_EXPORT = 'Export unique';
    const LABEL_NOMADE_SESSION_HISTORY = 'session mobile';
    const LABEL_WEB_SESSION_HISTORY = 'session web';

    const LABEL_SCHEDULED_IMPORT = 'Import planifié';
    const LABEL_UNIQUE_IMPORT = 'Import unique';

    const LABEL_AVERAGE_TIME = 'Temps moyen';

    const CREATE_DROP_MOVEMENT_BY_ID_MANUFACTURING_ORDER_VALUE = 'manufacturingOrder';
    const CREATE_DROP_MOVEMENT_BY_ID_PRODUCTION_REQUEST_VALUE = 'productionRequest';

    const CREATE_DROP_MOVEMENT_BY_ID_MANUFACTURING_ORDER_LABEL = 'N° OF';
    const CREATE_DROP_MOVEMENT_BY_ID_PRODUCTION_REQUEST_LABEL = 'N° demande de production';

    const CREATE_DROP_MOVEMENT_BY_ID_FIELD_LABEL = "Création d'un mouvement de dépose de l'identifiant";
    CONST CREATED_IDENTIFIER_NATURE_FIELD_LABEL = "Nature de l'identifiant créé";

    const DEFAULT_COLOR = "#3353D7";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Attribute used for data warehouse, do not delete it
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: ReferenceArticle::class)]
    private Collection $referenceArticles;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Article::class)]
    private Collection $articles;

    #[ORM\ManyToOne(targetEntity: CategoryType::class, inversedBy: 'types')]
    private ?CategoryType $category = null;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Reception::class)]
    private Collection $receptions;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: 'App\Entity\DeliveryRequest\Demande')]
    private Collection $demandesLivraison;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Dispute::class)]
    private Collection $disputes;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Collecte::class)]
    private Collection $collectes;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: 'deliveryTypes')]
    private Collection $deliveryUsers;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: 'dispatchTypes')]
    private Collection $dispatchUsers;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: 'handlingTypes')]
    private Collection $handlingUsers;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $sendMailRequester = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $sendMailReceiver = null;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Dispatch::class)]
    private Collection $dispatches;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Arrivage::class)]
    private Collection $arrivals;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Statut::class, orphanRemoval: true)]
    private Collection $statuts;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Handling::class)]
    private Collection $handlings;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'dropTypes')]
    private ?Emplacement $dropLocation = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'pickTypes')]
    private ?Emplacement $pickLocation = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $suggestedDropLocations = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $suggestedPickLocations = [];

    #[ORM\OneToOne(mappedBy: 'type', targetEntity: AverageRequestTime::class, cascade: ['persist', 'remove'])]
    private ?AverageRequestTime $averageRequestTime = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private ?bool $notificationsEnabled = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $notificationsEmergencies = [];

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: RequestTemplate::class)]
    private Collection $requestTemplates;

    #[ORM\OneToMany(mappedBy: 'requestType', targetEntity: RequestTemplate::class)]
    private Collection $requestTypeTemplates;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: 'App\Entity\IOT\Sensor')]
    private Collection $sensors;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $color = null;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $logo = null;

    #[ORM\OneToOne(mappedBy: "type", targetEntity: TranslationSource::class, cascade: ["remove"])]
    private ?TranslationSource $labelTranslation = null;

    #[ORM\ManyToMany(targetEntity: TagTemplate::class, mappedBy: 'types')]
    private Collection $tags;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $defaultType = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $reusableStatuses = true;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: FreeFieldManagementRule::class, orphanRemoval: true)]
    private Collection $freeFieldManagementRules;

    #[ORM\Column(type: TYPES::STRING, length: 5, nullable: true)]
    private ?string $averageTime = null;

    #[ORM\Column(type: TYPES::STRING, nullable: true)]
    private ?string $createDropMovementById = null;

    #[ORM\ManyToOne(targetEntity: Nature::class, cascade: ["persist"])]
    private ?Nature $createdIdentifierNature = null;

    #[ORM\OneToOne(mappedBy: "type", targetEntity: SleepingStockPlan::class)]
    private ?SleepingStockPlan $sleepingStockPlan = null;

    public function __construct() {
        $this->referenceArticles = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->receptions = new ArrayCollection();
        $this->disputes = new ArrayCollection();
        $this->demandesLivraison = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->deliveryUsers = new ArrayCollection();
        $this->dispatchUsers = new ArrayCollection();
        $this->handlingUsers = new ArrayCollection();
        $this->dispatches = new ArrayCollection();
        $this->arrivals = new ArrayCollection();
        $this->statuts = new ArrayCollection();
        $this->handlings = new ArrayCollection();
        $this->requestTemplates = new ArrayCollection();
        $this->requestTypeTemplates = new ArrayCollection();
        $this->sensors = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->freeFieldManagementRules = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabelIn(Language|string $in,
                               Language|string|null $default = null): ?string {
        $in = LanguageHelper::clearLanguage($in);
        $default = LanguageHelper::clearLanguage($default);

        $translation = $this->getLabelTranslation();

        return $translation?->getTranslationIn($in, $default)?->getTranslation()
            ?: $this->getLabel()
            ?: '';
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferenceArticles(): Collection {
        return $this->referenceArticles;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): self {
        if (!$this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles[] = $referenceArticle;
            $referenceArticle->setType($this);
        }

        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self {
        if ($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getType() === $this) {
                $referenceArticle->setType(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?CategoryType {
        return $this->category;
    }

    public function setCategory(?CategoryType $category): self {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection {
        return $this->articles;
    }

    public function addArticle(Article $article): self {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setType($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getType() === $this) {
                $article->setType(null);
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
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setType($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getType() === $this) {
                $reception->setType(null);
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

    public function addCommentaire(Dispute $commentaire): self {
        if (!$this->disputes->contains($commentaire)) {
            $this->disputes[] = $commentaire;
            $commentaire->setType($this);
        }

        return $this;
    }

    public function removeCommentaire(Dispute $commentaire): self {
        if ($this->disputes->contains($commentaire)) {
            $this->disputes->removeElement($commentaire);
            // set the owning side to null (unless already changed)
            if ($commentaire->getType() === $this) {
                $commentaire->setType(null);
            }
        }

        return $this;
    }

    public function addDispute(Dispute $dispute): self {
        if (!$this->disputes->contains($dispute)) {
            $this->disputes[] = $dispute;
            $dispute->setType($this);
        }

        return $this;
    }

    public function removeDispute(Dispute $dispute): self {
        if ($this->disputes->contains($dispute)) {
            $this->disputes->removeElement($dispute);
            // set the owning side to null (unless already changed)
            if ($dispute->getType() === $this) {
                $dispute->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Demande[]
     */
    public function getDemandesLivraison(): Collection {
        return $this->demandesLivraison;
    }

    public function addDemandesLivraison(Demande $demandesLivraison): self {
        if (!$this->demandesLivraison->contains($demandesLivraison)) {
            $this->demandesLivraison[] = $demandesLivraison;
            $demandesLivraison->setType($this);
        }

        return $this;
    }

    public function removeDemandesLivraison(Demande $demandesLivraison): self {
        if ($this->demandesLivraison->contains($demandesLivraison)) {
            $this->demandesLivraison->removeElement($demandesLivraison);
            // set the owning side to null (unless already changed)
            if ($demandesLivraison->getType() === $this) {
                $demandesLivraison->setType(null);
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
        if (!$this->collectes->contains($collecte)) {
            $this->collectes[] = $collecte;
            $collecte->setType($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if ($collecte->getType() === $this) {
                $collecte->setType(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getDeliveryUsers(): Collection {
        return $this->deliveryUsers;
    }

    public function addDeliveryUser(Utilisateur $user): self {
        if (!$this->deliveryUsers->contains($user)) {
            $this->deliveryUsers[] = $user;
            $user->addDeliveryType($this);
        }

        return $this;
    }

    public function removeDeliveryUser(Utilisateur $user): self {
        if ($this->deliveryUsers->contains($user)) {
            $this->deliveryUsers->removeElement($user);
            $user->removeDeliveryType($this);
        }

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getDispatchUsers(): Collection {
        return $this->dispatchUsers;
    }

    public function addDispatchUser(Utilisateur $user): self {
        if (!$this->dispatchUsers->contains($user)) {
            $this->dispatchUsers[] = $user;
            $user->addDispatchType($this);
        }

        return $this;
    }

    public function removeDispatchUser(Utilisateur $user): self {
        if ($this->dispatchUsers->contains($user)) {
            $this->dispatchUsers->removeElement($user);
            $user->removeDispatchType($this);
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getHandlingUsers(): Collection {
        return $this->handlingUsers;
    }

    public function addHandlingUser(Utilisateur $user): self {
        if (!$this->handlingUsers->contains($user)) {
            $this->handlingUsers[] = $user;
            $user->addHandlingType($this);
        }

        return $this;
    }

    public function removeHandlingUser(Utilisateur $user): self {
        if ($this->handlingUsers->contains($user)) {
            $this->handlingUsers->removeElement($user);
            $user->removeHandlingType($this);
        }

        return $this;
    }

    public function getSendMailRequester(): ?bool {
        return $this->sendMailRequester;
    }

    public function setSendMailRequester(?bool $sendMailRequester): self {
        $this->sendMailRequester = $sendMailRequester;

        return $this;
    }

    public function getSendMailReceiver(): ?bool {
        return $this->sendMailReceiver;
    }

    public function setSendMailReceiver(?bool $sendMailReceiver): self {
        $this->sendMailReceiver = $sendMailReceiver;

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getDispatches(): Collection {
        return $this->dispatches;
    }

    public function addDispatch(Dispatch $dispatch): self {
        if (!$this->dispatches->contains($dispatch)) {
            $this->dispatches[] = $dispatch;
            $dispatch->setType($this);
        }

        return $this;
    }

    public function removeDispatch(Dispatch $dispatch): self {
        if ($this->dispatches->contains($dispatch)) {
            $this->dispatches->removeElement($dispatch);
            // set the owning side to null (unless already changed)
            if ($dispatch->getType() === $this) {
                $dispatch->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivals(): Collection {
        return $this->arrivals;
    }

    public function addArrival(Arrivage $arrival): self {
        if (!$this->arrivals->contains($arrival)) {
            $this->arrivals[] = $arrival;
            $arrival->setType($this);
        }

        return $this;
    }

    public function removeArrival(Arrivage $arrival): self {
        if ($this->arrivals->contains($arrival)) {
            $this->arrivals->removeElement($arrival);
            // set the owning side to null (unless already changed)
            if ($arrival->getType() === $this) {
                $arrival->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Statut[]
     */
    public function getStatuts(): Collection {
        return $this->statuts;
    }

    public function addStatut(Statut $statut): self {
        if (!$this->statuts->contains($statut)) {
            $this->statuts[] = $statut;
            $statut->setType($this);
        }

        return $this;
    }

    public function removeStatut(Statut $statut): self {
        if ($this->statuts->contains($statut)) {
            $this->statuts->removeElement($statut);
            // set the owning side to null (unless already changed)
            if ($statut->getType() === $this) {
                $statut->setType(null);
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
        if (!$this->handlings->contains($handling)) {
            $this->handlings[] = $handling;
            $handling->setType($this);
        }

        return $this;
    }

    public function removeHandling(Handling $handling): self {
        if ($this->handlings->contains($handling)) {
            $this->handlings->removeElement($handling);
            // set the owning side to null (unless already changed)
            if ($handling->getType() === $this) {
                $handling->setType(null);
            }
        }

        return $this;
    }

    public function getDropLocation(): ?Emplacement {
        return $this->dropLocation;
    }

    public function setDropLocation(?Emplacement $dropLocation): self {
        $this->dropLocation = $dropLocation;

        return $this;
    }

    public function getPickLocation(): ?Emplacement {
        return $this->pickLocation;
    }

    public function setPickLocation(?Emplacement $pickLocation): self {
        $this->pickLocation = $pickLocation;

        return $this;
    }

    public function getAverageRequestTime(): ?AverageRequestTime {
        return $this->averageRequestTime;
    }

    public function setAverageRequestTime(AverageRequestTime $averageRequestTime): self {
        $this->averageRequestTime = $averageRequestTime;

        // set the owning side of the relation if necessary
        if ($averageRequestTime->getType() !== $this) {
            $averageRequestTime->setType($this);
        }

        return $this;
    }

    public function isNotificationsEnabled(): ?bool {
        return $this->notificationsEnabled;
    }

    public function setNotificationsEnabled(bool $notificationsEnabled): self {
        $this->notificationsEnabled = $notificationsEnabled;

        return $this;
    }

    public function getNotificationsEmergencies(): ?array {
        return $this->notificationsEmergencies;
    }

    public function isNotificationsEmergency(?string $emergency): bool {
        return (
            $emergency
            && (
                !empty($this->notificationsEmergencies)
                && in_array($emergency, $this->notificationsEmergencies)
            )
        );
    }

    public function setNotificationsEmergencies(?array $notificationsEmergencies): self {
        $this->notificationsEmergencies = $notificationsEmergencies;

        return $this;
    }

    /**
     * @return Collection|RequestTemplate[]
     */
    public function getRequestTemplates(): Collection {
        return $this->requestTemplates;
    }

    public function addRequestTemplate(RequestTemplate $requestTemplate): self {
        if (!$this->requestTemplates->contains($requestTemplate)) {
            $this->requestTemplates[] = $requestTemplate;
            $requestTemplate->setType($this);
        }

        return $this;
    }

    public function removeRequestTemplate(RequestTemplate $requestTemplate): self {
        if ($this->requestTemplates->removeElement($requestTemplate)) {
            // set the owning side to null (unless already changed)
            if ($requestTemplate->getType() === $this) {
                $requestTemplate->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|RequestTemplate[]
     */
    public function getRequestTypeTemplates(): Collection {
        return $this->requestTypeTemplates;
    }

    public function addRequestTypeTemplate(RequestTemplate $requestTypeTemplate): self {
        if (!$this->requestTypeTemplates->contains($requestTypeTemplate)) {
            $this->requestTypeTemplates[] = $requestTypeTemplate;
            $requestTypeTemplate->setRequestType($this);
        }

        return $this;
    }

    public function removeRequestTypeTemplate(RequestTemplate $requestTypeTemplate): self {
        if ($this->requestTypeTemplates->removeElement($requestTypeTemplate)) {
            // set the owning side to null (unless already changed)
            if ($requestTypeTemplate->getRequestType() === $this) {
                $requestTypeTemplate->setRequestType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Sensor[]
     */
    public function getSensors(): Collection {
        return $this->sensors;
    }

    public function addSensor(Sensor $sensor): self {
        if (!$this->sensors->contains($sensor)) {
            $this->sensors[] = $sensor;
            $sensor->setType($this);
        }

        return $this;
    }

    public function removeSensor(Sensor $sensor): self {
        if ($this->sensors->removeElement($sensor)) {
            if ($sensor->getType() === $this) {
                $sensor->setType(null);
            }
        }

        return $this;
    }

    public function getColor(): ?string {
        return $this->color;
    }

    public function setColor(?string $color): self {
        $this->color = $color;

        return $this;
    }

    public function getLogo(): ?Attachment {
        return $this->logo;
    }

    public function setLogo(?Attachment $logo): self {
        $this->logo = $logo;

        return $this;
    }

    public function getLabelTranslation(): ?TranslationSource {
        return $this->labelTranslation;
    }

    public function setLabelTranslation(?TranslationSource $labelTranslation): self {
        if($this->labelTranslation && $this->labelTranslation->getType() !== $this) {
            $oldLabelTranslation = $this->labelTranslation;
            $this->labelTranslation = null;
            $oldLabelTranslation->setType(null);
        }
        $this->labelTranslation = $labelTranslation;
        if($this->labelTranslation && $this->labelTranslation->getType() !== $this) {
            $this->labelTranslation->setType($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TagTemplate>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function getSuggestedDropLocations(): ?array {
        return $this->suggestedDropLocations;
    }


    public function setSuggestedDropLocations(?array $suggestedDropLocations): self {
        $this->suggestedDropLocations = [];
        foreach($suggestedDropLocations ?? [] as $suggestedDropLocation) {
            $this->suggestedDropLocations[] = $suggestedDropLocation;
        }

        return $this;
    }

    public function getSuggestedPickLocations(): ?array {
        return $this->suggestedPickLocations;
    }


    public function setSuggestedPickLocations(?array $suggestedPickLocations): self {
        $this->suggestedPickLocations = [];
        foreach($suggestedPickLocations ?? [] as $suggestedPickLocation) {
            $this->suggestedPickLocations[] = $suggestedPickLocation;
        }

        return $this;
    }

    public function setDefault(?bool $default): self {
        $this->defaultType = $default;

        return $this;
    }

    public function isDefault(): ?bool {
        return $this->defaultType;
    }

    public function setReusableStatuses(?bool $reusableStatuses): self {
        $this->reusableStatuses = $reusableStatuses;

        return $this;
    }

    public function hasReusableStatuses(): ?bool {
        return $this->reusableStatuses;
    }

    public function setActive(?bool $active): self {
        $this->active = $active;

        return $this;
    }

    public function isActive(): ?bool {
        return $this->active;
    }

    /**
     * @return Collection<int, FreeFieldManagementRule>
     */
    public function getFreeFieldManagementRules(): Collection
    {
        return $this->freeFieldManagementRules;
    }

    public function addFreeFieldManagementRule(FreeFieldManagementRule $freeFieldManagementRule): self
    {
        if (!$this->freeFieldManagementRules->contains($freeFieldManagementRule)) {
            $this->freeFieldManagementRules->add($freeFieldManagementRule);
            $freeFieldManagementRule->setType($this);
        }

        return $this;
    }

    public function removeFreeFieldManagementRule(FreeFieldManagementRule $freeFieldManagementRule): self
    {
        if ($this->freeFieldManagementRules->removeElement($freeFieldManagementRule)) {
            // set the owning side to null (unless already changed)
            if ($freeFieldManagementRule->getType() === $this) {
                $freeFieldManagementRule->setType(null);
            }
        }

        return $this;
    }

    public function getAverageTime(): ?string {
        return $this->averageTime;
    }

    public function setAverageTime(?string $averageTime): self {
        $this->averageTime = $averageTime;

        return $this;
    }

    public function getCreateDropMovementById(): ?string {
        return $this->createDropMovementById;
    }

    public function setCreateDropMovementById(?string $createDropMovementById): self {
        $this->createDropMovementById = $createDropMovementById;

        return $this;
    }

    public function getCreatedIdentifierNature(): ?Nature {
        return $this->createdIdentifierNature;
    }

    public function setCreatedIdentifierNature(?Nature $createdIdentifierNature): self {
        $this->createdIdentifierNature = $createdIdentifierNature;

        return $this;
    }

    public function getSleepingStockPlan(): ?SleepingStockPlan {
        return $this->sleepingStockPlan;
    }

    public function setSleepingStockPlan(?SleepingStockPlan $sleepingStockPlan): self {
        if($this->sleepingStockPlan && $this->sleepingStockPlan->getType() !== $this) {
            $oldSleepingStockPlan = $this->sleepingStockPlan;
            $this->sleepingStockPlan = null;
            $oldSleepingStockPlan->setType(null);
        }
        $this->sleepingStockPlan = $sleepingStockPlan;
        if($this->sleepingStockPlan && $this->sleepingStockPlan->getType() !== $this){
            $this->sleepingStockPlan->setType($this);
        }

        return $this;
    }
}
