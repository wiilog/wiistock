<?php
namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\PreparationOrder\Preparation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

use App\Entity\IOT\SensorWrapper;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UtilisateurRepository")
 * @UniqueEntity(fields="email", message="Cette adresse email est déjà utilisée.")
 * @UniqueEntity(fields="username", message="Ce nom d'utilisateur est déjà utilisé.")
 */
class Utilisateur implements UserInterface, EquatableInterface, PasswordAuthenticatedUserInterface
{
	const DEFAULT_ARTICLE_VISIBLE_COLUMNS = ["actions", "label", "reference", "articleReference", "type", "quantity", "location"];
    const DEFAULT_REFERENCE_VISIBLE_COLUMNS = ["actions", "label", "reference", "type", "quantity", "location"];
    const DEFAULT_ARRIVAL_VISIBLE_COLUMNS = ["date", "numeroArrivage", "transporteur", "chauffeur", "noTracking", "NumeroCommandeList", "fournisseur", "destinataire", "acheteurs", "NbUM", "customs", "frozen", "Statut", "Utilisateur", "urgent", "actions"];
    const DEFAULT_DISPATCH_VISIBLE_COLUMNS = ["number", "creationDate", "validationDate", "treatmentDate", "type", "requester", "receiver", "locationFrom", "locationTo", "nbPacks", "status", "emergency", "actions"];
    const DEFAULT_TRACKING_MOVEMENT_VISIBLE_COLUMNS = ["origin", "date", "colis", "reference", "label", "quantity", "location", "type", "operateur", "group"];
    const DEFAULT_DISPUTE_VISIBLE_COLUMNS = ["type", "arrivalNumber", "receptionNumber", "buyers", "numCommandeBl", "command", "provider", "references", "lastHistorique", "creationDate", "updateDate", "status", "actions"];
    const DEFAULT_RECEPTION_VISIBLE_COLUMNS = ["actions", "Date", "number", "dateAttendue", "DateFin", "orderNumber", "receiver", "Fournisseur", "Statut", "Commentaire", "deliveries", "storageLocation"];
    const DEFAULT_DELIVERY_REQUEST_VISIBLE_COLUMNS = ["actions", "pairing", "createdAt", "validatedAt", "requester", "number", "status", "type"];

    const DEFAULT_VISIBLE_COLUMNS = [
        'reference' => self::DEFAULT_REFERENCE_VISIBLE_COLUMNS,
        'article' => self::DEFAULT_ARTICLE_VISIBLE_COLUMNS,
        'arrival' => self::DEFAULT_ARRIVAL_VISIBLE_COLUMNS,
        'dispatch' => self::DEFAULT_DISPATCH_VISIBLE_COLUMNS,
        'dispute' => self::DEFAULT_DISPUTE_VISIBLE_COLUMNS,
        'trackingMovement' => self::DEFAULT_TRACKING_MOVEMENT_VISIBLE_COLUMNS,
        'reception' => self::DEFAULT_RECEPTION_VISIBLE_COLUMNS,
        'deliveryRequest' => self::DEFAULT_DELIVERY_REQUEST_VISIBLE_COLUMNS
    ];

    const SEARCH_DEFAULT = ["label", "reference"];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="Le champ ne peut pas être vide.")
     */
    private $username;
    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\NotBlank(message="Le champ ne peut pas être vide.")
     * @Assert\Email(message="Le format de l'adresse email n'est pas valide.")
     */
    private $email;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $token;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\FiltreSup", mappedBy="user")
     */
    private $filtresSup;

    /**
     * @Assert\Length(min=8, max=4096, minMessage="Le mot de passe doit contenir 8 caractères minimum.", maxMessage="Le mot de passe est trop long.")
     * @Assert\NotBlank(message="Le champ ne peut pas être vide.")
     */
    private $plainPassword;
    /**
     * @ORM\Column(type="array")
     */
    private $roles;
    /**
     * @ORM\Column(type="boolean")
     */
    private $status;
    /**
     * @ORM\ManyToOne(targetEntity="Role", inversedBy="users")
     */
    private $role;

    private $salt;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastLogin;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $address;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="utilisateur")
     */
    private $receptions;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DeliveryRequest\Demande", mappedBy="utilisateur")
     */
    private $demandes;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collecte", mappedBy="demandeur")
     */
    private $collectes;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PreparationOrder\Preparation", mappedBy="utilisateur")
     */
    private $preparations;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Livraison", mappedBy="utilisateur")
     */
    private $livraisons;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="user")
     */
    private $mouvements;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $apiKey;

    /**
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     */
    private $mobileLoginKey;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Handling", mappedBy="requester")
     */
    private $handlings;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Dispatch", mappedBy="receivers")
     */
    private $receivedDispatches;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Dispatch", mappedBy="requester")
     */
    private $requestedDispatches;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Dispatch", mappedBy="treatedBy")
     */
    private $treatedDispatches;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Handling", mappedBy="treatedByHandling")
     */
    private $treatedHandlings;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\FiltreRef", mappedBy="utilisateur", orphanRemoval=true)
     */
    private $filters;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\OrdreCollecte", mappedBy="utilisateur")
     */
    private $ordreCollectes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="destinataire")
     */
    private $arrivagesDestinataire;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Urgence", mappedBy="buyer")
     */
    private $emergencies;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Arrivage", mappedBy="acheteurs")
     */
    private $arrivagesAcheteur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="utilisateur")
     */
    private $arrivagesUtilisateur;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $recherche;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Type", inversedBy="deliveryUsers")
     * @ORM\JoinTable(name="user_delivery_type")
     */
    private $deliveryTypes;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Type", inversedBy="dispatchUsers")
     * @ORM\JoinTable(name="user_dispatch_type")
     */
    private $dispatchTypes;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Type", inversedBy="handlingUsers")
     * @ORM\JoinTable(name="user_handling_type")
     */
    private $handlingTypes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\InventoryEntry", mappedBy="operator")
     */
    private $inventoryEntries;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\InventoryCategoryHistory", inversedBy="operator")
     */
    private $inventoryCategoryHistory;

    /**
     * @ORM\OneToMany(targetEntity=DisputeHistoryRecord::class, mappedBy="user")
     */
    private Collection $disputeHistoryRecords;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceiptAssociation", mappedBy="user")
     */
    private $receptionsTraca;

    /**
     * @ORM\ManyToMany(targetEntity=Dispute::class, mappedBy="buyers")
     */
    private $disputes;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $rechercheForArticle;

    /**
     * @ORM\Column(type="integer", options={"unsigned":true})
     */
    private $pageLengthForArrivage = 100;

    /**
     * @var array|null
     * @ORM\Column(type="json")
     */
    private $savedDispatchDeliveryNoteData;

    /**
     * @var array|null
     * @ORM\Column(type="json")
     */
    private $savedDispatchWaybillData;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    private $phone;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="utilisateurs")
     */
    private $dropzone;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="userThatTriggeredEmergency")
     */
    private $referencesEmergenciesTriggered;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $secondaryEmails = [];

    /**
     * @ORM\OneToMany(targetEntity=Dispute::class, mappedBy="reporter")
     */
    private Collection $reportedDisputes;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="managers")
     */
    private $referencesArticle;

    /**
     * @ORM\ManyToMany(targetEntity=Handling::class, mappedBy="receivers")
     */
    private $receivedHandlings;

    /**
     * @ORM\OneToMany(targetEntity=ReferenceArticle::class, mappedBy="buyer")
     */
    private $referencesBuyer;

    /**
     * @ORM\OneToOne(targetEntity=Cart::class, mappedBy="user", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Cart $cart = null;

    /**
     * @ORM\OneToMany(targetEntity=PurchaseRequest::class, mappedBy="requester")
     */
    private ?Collection $purchaseRequestRequesters;

    /**
     * @ORM\OneToMany(targetEntity=PurchaseRequest::class, mappedBy="buyer")
     */
    private ?Collection $purchaseRequestBuyers;

    /**
     * @ORM\OneToMany(targetEntity=SensorWrapper::class, mappedBy="manager")
     */
    private Collection $sensorWrappers;

    /**
     * @ORM\ManyToMany(targetEntity=Notification::class, mappedBy="users")
     */
    private $unreadNotifications;

    /**
     * @ORM\ManyToMany(targetEntity=VisibilityGroup::class, mappedBy="users")
     */
    private Collection $visibilityGroups;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $columnsOrder = [];

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $searches = [];

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $pageIndexes = [];

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private array $visibleColumns;

    public function __construct()
    {
        $this->receptions = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->preparations = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->handlings = new ArrayCollection();
        $this->filters = new ArrayCollection();
        $this->ordreCollectes = new ArrayCollection();
        $this->arrivagesDestinataire = new ArrayCollection();
        $this->emergencies = new ArrayCollection();
        $this->arrivagesAcheteur = new ArrayCollection();
        $this->arrivagesUtilisateur = new ArrayCollection();
        $this->inventoryEntries = new ArrayCollection();
        $this->deliveryTypes = new ArrayCollection();
        $this->dispatchTypes = new ArrayCollection();
        $this->handlingTypes = new ArrayCollection();
        $this->filtresSup = new ArrayCollection();
        $this->disputeHistoryRecords = new ArrayCollection();
        $this->receivedDispatches = new ArrayCollection();
        $this->requestedDispatches = new ArrayCollection();
        $this->treatedDispatches = new ArrayCollection();
        $this->treatedHandlings = new ArrayCollection();
        $this->receptionsTraca = new ArrayCollection();
        $this->disputes = new ArrayCollection();
        $this->referencesEmergenciesTriggered = new ArrayCollection();
        $this->reportedDisputes = new ArrayCollection();
        $this->referencesArticle = new ArrayCollection();
        $this->receivedHandlings = new ArrayCollection();
        $this->referencesBuyer = new ArrayCollection();
        $this->purchaseRequestBuyers = new ArrayCollection();
        $this->purchaseRequestRequesters = new ArrayCollection();
        $this->sensorWrappers = new ArrayCollection();
        $this->unreadNotifications = new ArrayCollection();
        $this->visibilityGroups = new ArrayCollection();

        $this->secondaryEmails = [];
        $this->savedDispatchDeliveryNoteData = [];
        $this->savedDispatchWaybillData = [];

        $this->recherche = Utilisateur::SEARCH_DEFAULT;
        $this->rechercheForArticle = Utilisateur::SEARCH_DEFAULT;
        $this->roles = ['USER']; // évite bug -> champ roles ne doit pas être vide
        $this->visibleColumns = self::DEFAULT_VISIBLE_COLUMNS;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getUsername(): ?string
    {
        return $this->username;
    }
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getMainAndSecondaryEmails(): array {
        $secondaryEmails = array_filter(($this->secondaryEmails ?? []), function(string $email) {
            return !empty($email);
        });
        return array_merge(
            [$this->email],
            $secondaryEmails
        );
    }

    public function getPassword(): ?string
{
    return $this->password;
}
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }
    public function getToken(): ?string
    {
        return $this->token;
    }
    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }
    public function getPlainPassword()
    {
        return $this->plainPassword;
    }
    public function setPlainPassword($password)
    {
        $this->plainPassword = $password;
    }
    public function getSalt()
    {
        // you *may* need a real salt depending on your encoder
        // see section on salt below
        return null;
    }
    public function getRoles()
    {
        return $this->roles;
    }
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }
    public function eraseCredentials()
    { }
    public function isEqualTo(UserInterface $user)
    {
        if (!$user instanceof Utilisateur) {
            return false;
        }
        if ($this->password !== $user->getPassword()) {
            return false;
        }
        if ($this->email !== $user->getEmail()) {
            return false;
        }
        return true;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }
    public function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }
    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions(): Collection
    {
        return $this->receptions;
    }
    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setUtilisateur($this);
        }
        return $this;
    }
    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getUtilisateur() === $this) {
                $reception->setUtilisateur(null);
            }
        }
        return $this;
    }
    public function __toString(): ?string
    {
        // Attention le toString est utilisé pour l'unicité, getAcheteurs dans les arrivages notamment
        return $this->username;
    }
    /**
     * @return Collection|Demande[]
     */
    public function getDemandes(): Collection
    {
        return $this->demandes;
    }
    public function addDemande(Demande $demande): self
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes[] = $demande;
            $demande->setUtilisateur($this);
        }
        return $this;
    }
    public function removeDemande(Demande $demande): self
    {
        if ($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if ($demande->getUtilisateur() === $this) {
                $demande->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|Collecte[]
     */
    public function getCollectes(): Collection
    {
        return $this->collectes;
    }
    public function addCollecte(Collecte $collecte): self
    {
        if (!$this->collectes->contains($collecte)) {
            $this->collectes[] = $collecte;
            $collecte->setDemandeur($this);
        }
        return $this;
    }
    public function removeCollecte(Collecte $collecte): self
    {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if ($collecte->getDemandeur() === $this) {
                $collecte->setDemandeur(null);
            }
        }
        return $this;
    }
    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations(): Collection
    {
        return $this->preparations;
    }
    public function addPreparation(Preparation $preparation): self
    {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setUtilisateur($this);
        }
        return $this;
    }
    public function removePreparation(Preparation $preparation): self
    {
        if ($this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if ($preparation->getUtilisateur() === $this) {
                $preparation->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|Livraison[]
     */
    public function getLivraisons(): Collection
    {
        return $this->livraisons;
    }

    public function addLivraison(Livraison $livraison): self
    {
        if (!$this->livraisons->contains($livraison)) {
            $this->livraisons[] = $livraison;
            $livraison->setUtilisateur($this);
        }

        return $this;
    }

    public function removeLivraison(Livraison $livraison): self
    {
        if ($this->livraisons->contains($livraison)) {
            $this->livraisons->removeElement($livraison);
            // set the owning side to null (unless already changed)
            if ($livraison->getUtilisateur() === $this) {
                $livraison->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection
    {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self
    {
        if (!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setUser($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getUser() === $this) {
                $mouvement->setUser(null);
            }
        }

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * @return Collection|Handling[]
     */
    public function getHandlings(): Collection
    {
        return $this->handlings;
    }

    public function addHandling(Handling $handling): self
    {
        if (!$this->handlings->contains($handling)) {
            $this->handlings[] = $handling;
            $handling->setRequester($this);
        }

        return $this;
    }

    public function removeHandling(Handling $handling): self
    {
        if ($this->handlings->contains($handling)) {
            $this->handlings->removeElement($handling);
            // set the owning side to null (unless already changed)
            if ($handling->getRequester() === $this) {
                $handling->setRequester(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|FiltreRef[]
     */
    public function getFilters(): Collection
    {
        return $this->filters;
    }

    public function addFilter(FiltreRef $filter): self
    {
        if (!$this->filters->contains($filter)) {
            $this->filters[] = $filter;
            $filter->setUtilisateur($this);
        }

        return $this;
    }

    public function removeFilter(FiltreRef $filter): self
    {
        if ($this->filters->contains($filter)) {
            $this->filters->removeElement($filter);
            // set the owning side to null (unless already changed)
            if ($filter->getUtilisateur() === $this) {
                $filter->setUtilisateur(null);
            }
        }

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection|OrdreCollecte[]
     */
    public function getOrdreCollectes(): Collection
    {
        return $this->ordreCollectes;
    }

    public function addOrdreCollecte(OrdreCollecte $ordreCollecte): self
    {
        if (!$this->ordreCollectes->contains($ordreCollecte)) {
            $this->ordreCollectes[] = $ordreCollecte;
            $ordreCollecte->setUtilisateur($this);
        }

        return $this;
    }

    public function removeOrdreCollecte(OrdreCollecte $ordreCollecte): self
    {
        if ($this->ordreCollectes->contains($ordreCollecte)) {
            $this->ordreCollectes->removeElement($ordreCollecte);
            // set the owning side to null (unless already changed)
            if ($ordreCollecte->getUtilisateur() === $this) {
                $ordreCollecte->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivagesDestinataire(): Collection
    {
        return $this->arrivagesDestinataire;
    }

    public function addArrivage(Arrivage $arrivage): self
    {
        if (!$this->arrivagesDestinataire->contains($arrivage)) {
            $this->arrivagesDestinataire[] = $arrivage;
            $arrivage->setDestinataire($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self
    {
        if ($this->arrivagesDestinataire->contains($arrivage)) {
            $this->arrivagesDestinataire->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if ($arrivage->getDestinataire() === $this) {
                $arrivage->setDestinataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Urgence[]
     */
    public function getEmergencies(): Collection
    {
        return $this->emergencies;
    }

    public function addEmergency(Urgence $urgence): self
    {
        if (!$this->emergencies->contains($urgence)) {
            $this->emergencies[] = $urgence;
            $urgence->setBuyer($this);
        }

        return $this;
    }

    public function removeEmergency(Urgence $urgence): self
    {
        if ($this->emergencies->contains($urgence)) {
            $this->emergencies->removeElement($urgence);
            // set the owning side to null (unless already changed)
            if ($urgence->getBuyer() === $this) {
                $urgence->setBuyer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivagesAcheteur(): Collection
    {
        return $this->arrivagesAcheteur;
    }

    public function addArrivagesAcheteur(Arrivage $arrivagesAcheteur): self
    {
        if (!$this->arrivagesAcheteur->contains($arrivagesAcheteur)) {
            $this->arrivagesAcheteur[] = $arrivagesAcheteur;
            $arrivagesAcheteur->addAcheteur($this);
        }

        return $this;
    }

    public function removeArrivagesAcheteur(Arrivage $arrivagesAcheteur): self
    {
        if ($this->arrivagesAcheteur->contains($arrivagesAcheteur)) {
            $this->arrivagesAcheteur->removeElement($arrivagesAcheteur);
            $arrivagesAcheteur->removeAcheteur($this);
        }

        return $this;
    }

    public function addArrivagesDestinataire(Arrivage $arrivagesDestinataire): self
    {
        if (!$this->arrivagesDestinataire->contains($arrivagesDestinataire)) {
            $this->arrivagesDestinataire[] = $arrivagesDestinataire;
            $arrivagesDestinataire->setDestinataire($this);
        }

        return $this;
    }

    public function removeArrivagesDestinataire(Arrivage $arrivagesDestinataire): self
    {
        if ($this->arrivagesDestinataire->contains($arrivagesDestinataire)) {
            $this->arrivagesDestinataire->removeElement($arrivagesDestinataire);
            // set the owning side to null (unless already changed)
            if ($arrivagesDestinataire->getDestinataire() === $this) {
                $arrivagesDestinataire->setDestinataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivagesUtilisateur(): Collection
    {
        return $this->arrivagesUtilisateur;
    }

    public function addArrivagesUtilisateur(Arrivage $arrivagesUtilisateur): self
    {
        if (!$this->arrivagesUtilisateur->contains($arrivagesUtilisateur)) {
            $this->arrivagesUtilisateur[] = $arrivagesUtilisateur;
            $arrivagesUtilisateur->setUtilisateur($this);
        }

        return $this;
    }

    public function removeArrivagesUtilisateur(Arrivage $arrivagesUtilisateur): self
    {
        if ($this->arrivagesUtilisateur->contains($arrivagesUtilisateur)) {
            $this->arrivagesUtilisateur->removeElement($arrivagesUtilisateur);
            // set the owning side to null (unless already changed)
            if ($arrivagesUtilisateur->getUtilisateur() === $this) {
                $arrivagesUtilisateur->setUtilisateur(null);
            }
        }

        return $this;
    }

    public function getRecherche()
    {
        return $this->recherche;
    }

    public function setRecherche($recherche): self
    {
        $this->recherche = $recherche;

        return $this;
    }

    /**
     * @return ArrayCollection|Type[]
     */
    public function getDeliveryTypes()
    {
        return $this->deliveryTypes;
    }

    /**
     * @return int[]
     */
    public function getDeliveryTypeIds(): array {
        return $this->deliveryTypes
            ->map(function (Type $type) {return $type->getId();})
            ->toArray();
    }

    public function addDeliveryType(Type $type): self
    {
        if (!$this->deliveryTypes->contains($type)) {
            $this->deliveryTypes[] = $type;
        }

        return $this;
    }

    public function removeDeliveryType(Type $type): self
    {
        if ($this->deliveryTypes->contains($type)) {
            $this->deliveryTypes->removeElement($type);
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getDispatchTypes(): Collection
    {
        return $this->dispatchTypes;
    }

    /**
     * @return int[]
     */
    public function getDispatchTypeIds(): array {
        return $this->dispatchTypes
            ->map(function (Type $type) {return $type->getId();})
            ->toArray();
    }

    public function addDispatchType(Type $type): self
    {
        if (!$this->dispatchTypes->contains($type)) {
            $this->dispatchTypes[] = $type;
        }

        return $this;
    }

    public function removeDispatchType(Type $type): self
    {
        if ($this->dispatchTypes->contains($type)) {
            $this->dispatchTypes->removeElement($type);
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getHandlingTypes(): Collection {
        return $this->handlingTypes;
    }

    /**
     * @return int[]
     */
    public function getHandlingTypeIds(): array {
        return $this->handlingTypes
            ->map(function (Type $type) {return $type->getId();})
            ->toArray();
    }

    public function addHandlingType(Type $type): self
    {
        if (!$this->handlingTypes->contains($type)) {
            $this->handlingTypes[] = $type;
        }

        return $this;
    }

    public function removeHandlingType(Type $type): self
    {
        if ($this->handlingTypes->contains($type)) {
            $this->handlingTypes->removeElement($type);
        }

        return $this;
    }


    /**
     * @return Collection|InventoryEntry[]
     */
    public function getInventoryEntries(): Collection
    {
        return $this->inventoryEntries;
    }

    public function addInventoryEntry(InventoryEntry $inventoryEntry): self
    {
        if (!$this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries[] = $inventoryEntry;
            $inventoryEntry->setOperator($this);
        }

        return $this;
    }

    public function removeInventoryEntry(InventoryEntry $inventoryEntry): self
    {
        if ($this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries->removeElement($inventoryEntry);
            // set the owning side to null (unless already changed)
            if ($inventoryEntry->getOperator() === $this) {
                $inventoryEntry->setOperator(null);
            }
        }

        return $this;
    }

    public function getInventoryCategoryHistory(): ?InventoryCategoryHistory
    {
        return $this->inventoryCategoryHistory;
    }

    public function setInventoryCategoryHistory(?InventoryCategoryHistory $inventoryCategoryHistory): self
    {
        $this->inventoryCategoryHistory = $inventoryCategoryHistory;

        return $this;
    }

    /**
     * @return Collection|FiltreSup[]
     */
    public function getFiltresSup(): Collection
    {
        return $this->filtresSup;
    }

    public function addFiltresSup(FiltreSup $filtresSup): self
    {
        if (!$this->filtresSup->contains($filtresSup)) {
            $this->filtresSup[] = $filtresSup;
            $filtresSup->setUser($this);
        }

        return $this;
    }

    public function removeFiltresSup(FiltreSup $filtresSup): self
    {
        if ($this->filtresSup->contains($filtresSup)) {
            $this->filtresSup->removeElement($filtresSup);
            // set the owning side to null (unless already changed)
            if ($filtresSup->getUser() === $this) {
                $filtresSup->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DisputeHistoryRecord[]
     */
    public function getDisputeHistoryRecords(): Collection
    {
        return $this->disputeHistoryRecords;
    }

    public function addDisputeHistoryRecord(DisputeHistoryRecord $record): self
    {
        if (!$this->disputeHistoryRecords->contains($record)) {
            $this->disputeHistoryRecords[] = $record;
            $record->setUser($this);
        }

        return $this;
    }

    public function removeDisputeHistoryRecord(DisputeHistoryRecord $record): self
    {
        if ($this->disputeHistoryRecords->contains($record)) {
            $this->disputeHistoryRecords->removeElement($record);
            // set the owning side to null (unless already changed)
            if ($record->getUser() === $this) {
                $record->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getReceivedDispatches(): Collection
    {
        return $this->receivedDispatches;
    }

    public function addReceivedDispatch(Dispatch $receivedDispatch): self
    {
        if (!$this->receivedDispatches->contains($receivedDispatch)) {
            $this->receivedDispatches[] = $receivedDispatch;
            $receivedDispatch->addReceiver($this);
        }

        return $this;
    }

    public function removeReceivedDispatch(Dispatch $receivedDispatch): self
    {
        if ($this->receivedDispatches->contains($receivedDispatch)) {
            $this->receivedDispatches->removeElement($receivedDispatch);
            // set the owning side to null (unless already changed)
            if ($receivedDispatch->getReceivers()->contains($this)) {
                $receivedDispatch->removeReceiver($this);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getRequestedDispatches(): Collection
    {
        return $this->requestedDispatches;
    }

    public function addRequestedDispatch(Dispatch $requestedDispatch): self
    {
        if (!$this->requestedDispatches->contains($requestedDispatch)) {
            $this->requestedDispatches[] = $requestedDispatch;
            $requestedDispatch->setRequester($this);
        }

        return $this;
    }

    public function removeRequestedDispatch(Dispatch $requestedDispatch): self
    {
        if ($this->requestedDispatches->contains($requestedDispatch)) {
            $this->requestedDispatches->removeElement($requestedDispatch);
            // set the owning side to null (unless already changed)
            if ($requestedDispatch->getRequester() === $this) {
                $requestedDispatch->setRequester(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getTreatedDispatches(): Collection
    {
        return $this->treatedDispatches;
    }

    public function addTreatedDispatch(Dispatch $treatedDispatch): self
    {
        if (!$this->treatedDispatches->contains($treatedDispatch)) {
            $this->treatedDispatches[] = $treatedDispatch;
            $treatedDispatch->setRequester($this);
        }

        return $this;
    }

    public function removeTreatedDispatch(Dispatch $treatedDispatch): self
    {
        if ($this->treatedDispatches->contains($treatedDispatch)) {
            $this->treatedDispatches->removeElement($treatedDispatch);
            // set the owning side to null (unless already changed)
            if ($treatedDispatch->getRequester() === $this) {
                $treatedDispatch->setRequester(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getTreatedHandlings(): Collection
    {
        return $this->treatedHandlings;
    }

    public function addTreatedHandling(Dispatch $treatedHandling): self
    {
        if (!$this->treatedHandlings->contains($treatedHandling)) {
            $this->treatedHandlings[] = $treatedHandling;
            $treatedHandling->setRequester($this);
        }

        return $this;
    }

    public function removeTreatedHandling(Dispatch $treatedHandling): self
    {
        if ($this->treatedHandlings->contains($treatedHandling)) {
            $this->treatedHandlings->removeElement($treatedHandling);
            // set the owning side to null (unless already changed)
            if ($treatedHandling->getRequester() === $this) {
                $treatedHandling->setRequester(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReceiptAssociation[]
     */
    public function getReceptionsTraca(): Collection
    {
        return $this->receptionsTraca;
    }

    public function addReceptionsTraca(ReceiptAssociation $receptionsTraca): self
    {
        if (!$this->receptionsTraca->contains($receptionsTraca)) {
            $this->receptionsTraca[] = $receptionsTraca;
            $receptionsTraca->setUser($this);
        }

        return $this;
    }

    public function removeReceptionsTraca(ReceiptAssociation $receptionsTraca): self
    {
        if ($this->receptionsTraca->contains($receptionsTraca)) {
            $this->receptionsTraca->removeElement($receptionsTraca);
            // set the owning side to null (unless already changed)
            if ($receptionsTraca->getUser() === $this) {
                $receptionsTraca->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Dispute[]
     */
    public function getDisputes(): Collection
    {
        return $this->disputes;
    }

    public function addDispute(Dispute $dispute): self
    {
        if (!$this->disputes->contains($dispute)) {
            $this->disputes[] = $dispute;
            $dispute->addBuyer($this);
        }

        return $this;
    }

    public function removeDispute(Dispute $dispute): self
    {
        if ($this->disputes->contains($dispute)) {
            $this->disputes->removeElement($dispute);
            $dispute->removeBuyer($this);
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferencesArticle(): Collection
    {
        return $this->referencesArticle;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if (!$this->referencesArticle->contains($referenceArticle)) {
            $this->referencesArticle[] = $referenceArticle;
            $referenceArticle->addManager($this);
        }

        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if ($this->referencesArticle->contains($referenceArticle)) {
            $this->referencesArticle->removeElement($referenceArticle);
            $referenceArticle->removeManager($this);
        }

        return $this;
    }

    public function getRechercheForArticle(): array
    {
        return $this->rechercheForArticle;
    }

    public function setRechercheForArticle($rechercheForArticle): self
    {
        $this->rechercheForArticle = $rechercheForArticle;

        return $this;
    }

    public function getPageLengthForArrivage(): ?int
    {
        return $this->pageLengthForArrivage;
    }

    public function setPageLengthForArrivage(int $pageLengthForArrivage): self
    {
        $this->pageLengthForArrivage = $pageLengthForArrivage;

        return $this;
    }

    public function getDropzone(): ?Emplacement
    {
        return $this->dropzone;
    }

    public function setDropzone(?Emplacement $dropzone): self
    {
        $this->dropzone = $dropzone;

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferencesEmergenciesTriggered(): Collection
    {
        return $this->referencesEmergenciesTriggered;
    }

    public function addReferencesEmergenciesTriggered(ReferenceArticle $referencesEmergenciesTriggered): self
    {
        if (!$this->referencesEmergenciesTriggered->contains($referencesEmergenciesTriggered)) {
            $this->referencesEmergenciesTriggered[] = $referencesEmergenciesTriggered;
            $referencesEmergenciesTriggered->setUserThatTriggeredEmergency($this);
        }

        return $this;
    }

    public function removeReferencesEmergenciesTriggered(ReferenceArticle $referencesEmergenciesTriggered): self
    {
        if ($this->referencesEmergenciesTriggered->contains($referencesEmergenciesTriggered)) {
            $this->referencesEmergenciesTriggered->removeElement($referencesEmergenciesTriggered);
            // set the owning side to null (unless already changed)
            if ($referencesEmergenciesTriggered->getUserThatTriggeredEmergency() === $this) {
                $referencesEmergenciesTriggered->setUserThatTriggeredEmergency(null);
            }
        }

        return $this;
    }

    public function getSecondaryEmails(): ?array
    {
        return $this->secondaryEmails;
    }

    public function setSecondaryEmails(?array $secondaryEmails): self
    {
        $this->secondaryEmails = $secondaryEmails;

        return $this;
    }

    /**
     * @return Collection|Dispute[]
     */
    public function getReportedDisputes(): Collection
    {
        return $this->reportedDisputes;
    }

    public function addDisputeReporter(Dispute $dispute): self
    {
        if (!$this->reportedDisputes->contains($dispute)) {
            $this->reportedDisputes[] = $dispute;
            $dispute->setReporter($this);
        }

        return $this;
    }

    public function removeDisputeReporter(Dispute $dispute): self
    {
        if ($this->reportedDisputes->contains($dispute)) {
            $this->reportedDisputes->removeElement($dispute);
            // set the owning side to null (unless already changed)
            if ($dispute->getReporter() === $this) {
                $dispute->setReporter(null);
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getMobileLoginKey(): string {
        return $this->mobileLoginKey;
    }

    /**
     * @param string $mobileLoginKey
     * @return Utilisateur
     */
    public function setMobileLoginKey(string $mobileLoginKey): self {
        $this->mobileLoginKey = $mobileLoginKey;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getSavedDispatchDeliveryNoteData(): array {
        return $this->savedDispatchDeliveryNoteData ?? [];
    }

    /**
     * @param array|null $savedDispatchDeliveryNoteData
     * @return self
     */
    public function setSavedDispatchDeliveryNoteData(array $savedDispatchDeliveryNoteData): self {
        $this->savedDispatchDeliveryNoteData = $savedDispatchDeliveryNoteData;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getSavedDispatchWaybillData(): array {
        return $this->savedDispatchWaybillData ?? [];
    }

    /**
     * @param array $savedDispatchWaybillData
     * @return self
     */
    public function setSavedDispatchWaybillData(array $savedDispatchWaybillData): self {
        $this->savedDispatchWaybillData = $savedDispatchWaybillData;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string {
        return $this->phone;
    }

    /**
     * @param string|null $phone
     * @return self
     */
    public function setPhone(?string $phone): self {
        $this->phone = $phone;
        return $this;
    }

    /**
     * @return Collection|Handling[]
     */
    public function getReceivedHandlings(): Collection
    {
        return $this->receivedHandlings;
    }

    public function addReceivedHandling(Handling $handling): self
    {
        if (!$this->receivedHandlings->contains($handling)) {
            $this->receivedHandlings[] = $handling;
            if (!$handling->getReceivers()->contains($this)) {
                $handling->addReceiver($this);
            }
        }

        return $this;
    }

    public function removeReceivedHandling(Handling $handling): self
    {
        if ($this->receivedHandlings->removeElement($handling)) {
            $handling->removeReceiver($this);
        }

        return $this;
    }



    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferencesBuyer(): Collection
    {
        return $this->referencesBuyer;
    }

    public function getCart(): Cart
    {
        if(!$this->cart) {
            $this->cart = new Cart();
            $this->cart->setUser($this);
        }

        return $this->cart;
    }

    public function setCart(?Cart $cart): self
    {
        // unset the owning side of the relation if necessary
        if ($cart === null && $this->cart !== null) {
            $this->cart->setUser(null);
        }

        // set the owning side of the relation if necessary
        if ($cart !== null && $cart->getUser() !== $this) {
            $cart->setUser($this);
        }

        $this->cart = $cart;

        return $this;
    }

    /**
     * @return Collection|PurchaseRequest[]
     */
    public function getPurchaseRequestRequesters(): Collection
    {
        return $this->purchaseRequestRequesters;
    }

    public function addPurchaseRequestRequester(PurchaseRequest $purchaseRequestRequester): self
    {
        if (!$this->purchaseRequestRequesters->contains($purchaseRequestRequester)) {
            $this->purchaseRequestRequesters[] = $purchaseRequestRequester;
            $purchaseRequestRequester->setRequester($this);
        }

        return $this;
    }

    public function removePurchaseRequestRequester(PurchaseRequest $purchaseRequestRequester): self
    {
        if ($this->purchaseRequestRequesters->removeElement($purchaseRequestRequester)) {
            // set the owning side to null (unless already changed)
            if ($purchaseRequestRequester->getRequester() === $this) {
                $purchaseRequestRequester->setRequester(null);
            }
        }
        return $this;
    }

    public function setPurchaseRequestRequesters(?array $purchaseRequestRequesters): self {
        foreach($this->getPurchaseRequestRequesters()->toArray() as $purchaseRequestRequester) {
            $this->removePurchaseRequestRequester($purchaseRequestRequester);
        }

        $this->purchaseRequestRequesters = new ArrayCollection();
        foreach($purchaseRequestRequesters as $purchaseRequestRequester) {
            $this->addPurchaseRequestRequester($purchaseRequestRequester);
        }

        return $this;
    }

    /**
     * @return Collection|PurchaseRequest[]
     */
    public function getPurchaseRequestBuyers(): Collection
    {
        return $this->purchaseRequestBuyers;
    }

    public function addPurchaseRequestBuyer(PurchaseRequest $purchaseRequestBuyer): self
    {
        if (!$this->purchaseRequestBuyers->contains($purchaseRequestBuyer)) {
            $this->purchaseRequestBuyers[] = $purchaseRequestBuyer;
            $purchaseRequestBuyer->setBuyer($this);
        }

        return $this;
    }

    public function removePurchaseRequestBuyer(PurchaseRequest $purchaseRequestBuyer): self {
        if ($this->purchaseRequestBuyers->removeElement($purchaseRequestBuyer)) {
            if ($purchaseRequestBuyer->getBuyer() === $this) {
                $purchaseRequestBuyer->setBuyer(null);
            }
        }
        return $this;
    }

    public function setPurchaseRequestBuyers(?array $purchaseRequestBuyers): self {
        foreach($this->getPurchaseRequestBuyers()->toArray() as $purchaseRequestBuyer) {
            $this->removePurchaseRequestBuyer($purchaseRequestBuyer);
        }

        $this->purchaseRequestBuyers = new ArrayCollection();
        foreach($purchaseRequestBuyers as $purchaseRequestBuyer) {
            $this->addPurchaseRequestBuyer($purchaseRequestBuyer);
        }

        return $this;
    }

    /**
     * @return Collection|SensorWrapper[]
     */
    public function getSensorWrappers(): Collection
    {
        return $this->sensorWrappers;
    }

    public function addSensorWrapper(SensorWrapper $sensorWrapper): self
    {
        if (!$this->sensorWrappers->contains($sensorWrapper)) {
            $this->sensorWrappers[] = $sensorWrapper;
            $sensorWrapper->setManager($this);
        }

        return $this;
    }

    public function removeSensorWrapper(SensorWrapper $sensorWrapper): self
    {
        if ($this->sensorWrappers->removeElement($sensorWrapper)) {
            // set the owning side to null (unless already changed)
            if ($sensorWrapper->getManager() === $this) {
                $sensorWrapper->setManager(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Notification[]
     */
    public function getUnreadNotifications(): Collection
    {
        return $this->unreadNotifications;
    }

    public function clearNotifications()
    {
        foreach ($this->getUnreadNotifications() as $notification) {
            $this->removeUnreadNotification($notification);
        }
    }

    public function addUnreadNotification(Notification $unreadNotification): self
    {
        if (!$this->unreadNotifications->contains($unreadNotification)) {
            $this->unreadNotifications[] = $unreadNotification;
            $unreadNotification->addUser($this);
        }

        return $this;
    }

    public function removeUnreadNotification(Notification $unreadNotification): self
    {
        if ($this->unreadNotifications->removeElement($unreadNotification)) {
            $unreadNotification->removeUser($this);
        }

        return $this;
    }

    /**
     * @return Collection|VisibilityGroup[]
     */
    public function getVisibilityGroups(): Collection {
        return $this->visibilityGroups;
    }

    public function addVisibilityGroup(VisibilityGroup $visibilityGroup): self {
        if (!$this->visibilityGroups->contains($visibilityGroup)) {
            $this->visibilityGroups[] = $visibilityGroup;
            $visibilityGroup->addUser($this);
        }

        return $this;
    }

    public function removeVisibilityGroup(VisibilityGroup $visibilityGroup): self {
        if ($this->visibilityGroups->removeElement($visibilityGroup)) {
            $visibilityGroup->removeUser($this);
        }

        return $this;
    }

    public function setVisibilityGroups(?array $visibilityGroups): self {
        foreach($this->getVisibilityGroups()->toArray() as $visibilityGroup) {
            $this->removeVisibilityGroup($visibilityGroup);
        }

        $this->visibilityGroups = new ArrayCollection();
        foreach($visibilityGroups as $visibilityGroup) {
            $this->addVisibilityGroup($visibilityGroup);
        }

        return $this;
    }

    public function getColumnsOrder(): ?array
    {
        return $this->columnsOrder;
    }

    public function setColumnsOrder(?array $columnsOrder): self
    {
        $this->columnsOrder = $columnsOrder;

        return $this;
    }

    public function getSearches(): ?array
    {
        return $this->searches;
    }

    public function setSearches(?array $searches): self
    {
        $this->searches = $searches;

        return $this;
    }

    public function getPageIndexes(): ?array
    {
        return $this->pageIndexes;
    }

    public function setPageIndexes(?array $pagesIndexes): self
    {
        $this->pageIndexes = $pagesIndexes;

        return $this;
    }

    public function getVisibleColumns(): ?array
    {
        return $this->visibleColumns;
    }

    public function setVisibleColumns(?array $visibleColumns): self
    {
        $this->visibleColumns = $visibleColumns;

        return $this;
    }

}
