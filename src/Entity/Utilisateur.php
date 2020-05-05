<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UtilisateurRepository")
 * @UniqueEntity(fields="email", message="Cette adresse email est déjà utilisée.")
 * @UniqueEntity(fields="username", message="Ce nom d'utilisateur est déjà utilisé.")
 */
class Utilisateur implements UserInterface, EquatableInterface
{
	const COL_VISIBLE_ARTICLES_DEFAULT = ["Actions", "Libellé", "Référence", "Référence article", "Type", "Quantité", "Emplacement"];
	const COL_VISIBLE_REF_DEFAULT = ["Actions", "Libellé", "Référence", "Type", "Quantité", "Emplacement"];
	const SEARCH_DEFAULT = ["Libellé", "Référence"];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    private $username;
    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\NotBlank()
     * @Assert\Email()
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
     * @Assert\NotBlank()
     * @Assert\Length(min=8, max=4096)
     * @Assert\Regex(pattern="/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*\W)(?!.*\s).*$/", message="Doit contenir au moins une majuscule, une minuscule, un symbole, et un nombre.")
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
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="utilisateur")
     */
    private $receptions;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="utilisateur")
     */
    private $demandes;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collecte", mappedBy="demandeur")
     */
    private $collectes;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Preparation", mappedBy="utilisateur")
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
     * @ORM\OneToMany(targetEntity="App\Entity\Manutention", mappedBy="demandeur")
     */
    private $manutentions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Acheminements", mappedBy="receiver")
     */
    private $acheminementsReceive;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Acheminements", mappedBy="requester")
     */
    private $acheminementsRequester;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\FiltreRef", mappedBy="utilisateur", orphanRemoval=true)
     */
    private $filters;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $columnVisible = [];

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
     * @ORM\ManyToMany(targetEntity="App\Entity\Type", inversedBy="utilisateurs")
     */
    private $types;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\InventoryEntry", mappedBy="operator")
     */
    private $inventoryEntries;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\InventoryCategoryHistory", inversedBy="operator")
     */
    private $inventoryCategoryHistory;

    /**
     * @ORM\OneToMany(targetEntity="LitigeHistoric", mappedBy="user")
     */
    private $litigeHistorics;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceptionTraca", mappedBy="user")
     */
    private $receptionsTraca;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Litige", mappedBy="buyers")
     */
    private $litiges;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $rechercheForArticle;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $columnsVisibleForArticle;

    /**
     * @ORM\Column(type="integer", options={"unsigned":true})
     */
    private $pageLengthForArrivage = 100;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="utilisateurs")
     */
    private $dropzone;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="userThatTriggeredEmergency")
     */
    private $referencesEmergenciesTriggered;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $columnsVisibleForLitige;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $columnsVisibleForArrivage;

    public function __construct()
    {
        $this->receptions = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->preparations = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->manutentions = new ArrayCollection();
        $this->filters = new ArrayCollection();
        $this->ordreCollectes = new ArrayCollection();
        $this->arrivagesDestinataire = new ArrayCollection();
        $this->emergencies = new ArrayCollection();
        $this->arrivagesAcheteur = new ArrayCollection();
        $this->arrivagesUtilisateur = new ArrayCollection();
        $this->inventoryEntries = new ArrayCollection();
        $this->types = new ArrayCollection();
        $this->filtresSup = new ArrayCollection();
        $this->litigeHistorics = new ArrayCollection();
        $this->acheminementsReceive = new ArrayCollection();
        $this->acheminementsRequester = new ArrayCollection();
        $this->receptionsTraca = new ArrayCollection();
        $this->litiges = new ArrayCollection();
        $this->referencesEmergenciesTriggered = new ArrayCollection();
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
     * @return Collection|Manutention[]
     */
    public function getManutentions(): Collection
    {
        return $this->manutentions;
    }

    public function addManutention(Manutention $manutention): self
    {
        if (!$this->manutentions->contains($manutention)) {
            $this->manutentions[] = $manutention;
            $manutention->setDemandeur($this);
        }

        return $this;
    }

    public function removeManutention(Manutention $manutention): self
    {
        if ($this->manutentions->contains($manutention)) {
            $this->manutentions->removeElement($manutention);
            // set the owning side to null (unless already changed)
            if ($manutention->getDemandeur() === $this) {
                $manutention->setDemandeur(null);
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

    public function getColumnVisible(): ?array
    {
        return $this->columnVisible;
    }

    public function setColumnVisible(?array $columnVisible): self
    {
        $this->columnVisible = $columnVisible;

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
            $arrivagesUtilisateur->setUtilisateurs($this);
        }

        return $this;
    }

    public function removeArrivagesUtilisateur(Arrivage $arrivagesUtilisateur): self
    {
        if ($this->arrivagesUtilisateur->contains($arrivagesUtilisateur)) {
            $this->arrivagesUtilisateur->removeElement($arrivagesUtilisateur);
            // set the owning side to null (unless already changed)
            if ($arrivagesUtilisateur->getUtilisateurs() === $this) {
                $arrivagesUtilisateur->setUtilisateurs(null);
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
    public function getTypes()
    {
        return $this->types;
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

    public function addType(Type $type): self
    {
        if (!$this->types->contains($type)) {
            $this->types[] = $type;
        }

        return $this;
    }

    public function removeType(Type $type): self
    {
        if ($this->types->contains($type)) {
            $this->types->removeElement($type);
        }

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
     * @return Collection|LitigeHistoric[]
     */
    public function getLitigeHistorics(): Collection
    {
        return $this->litigeHistorics;
    }

    public function addLitigeHistory(LitigeHistoric $litigeHistory): self
    {
        if (!$this->litigeHistorics->contains($litigeHistory)) {
            $this->litigeHistorics[] = $litigeHistory;
            $litigeHistory->setUser($this);
        }

        return $this;
    }

    public function removeLitigeHistory(LitigeHistoric $litigeHistory): self
    {
        if ($this->litigeHistorics->contains($litigeHistory)) {
            $this->litigeHistorics->removeElement($litigeHistory);
            // set the owning side to null (unless already changed)
            if ($litigeHistory->getUser() === $this) {
                $litigeHistory->setUser(null);
            }
        }

        return $this;
    }

    public function addLitigeHistoric(LitigeHistoric $litigeHistoric): self
    {
        if (!$this->litigeHistorics->contains($litigeHistoric)) {
            $this->litigeHistorics[] = $litigeHistoric;
            $litigeHistoric->setUser($this);
        }

        return $this;
    }

    public function removeLitigeHistoric(LitigeHistoric $litigeHistoric): self
    {
        if ($this->litigeHistorics->contains($litigeHistoric)) {
            $this->litigeHistorics->removeElement($litigeHistoric);
            // set the owning side to null (unless already changed)
            if ($litigeHistoric->getUser() === $this) {
                $litigeHistoric->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Acheminements[]
     */
    public function getAcheminementsReceive(): Collection
    {
        return $this->acheminementsReceive;
    }

    public function addAcheminementsReceive(Acheminements $acheminementsReceive): self
    {
        if (!$this->acheminementsReceive->contains($acheminementsReceive)) {
            $this->acheminementsReceive[] = $acheminementsReceive;
            $acheminementsReceive->setReceiver($this);
        }

        return $this;
    }

    public function removeAcheminementsReceive(Acheminements $acheminementsReceive): self
    {
        if ($this->acheminementsReceive->contains($acheminementsReceive)) {
            $this->acheminementsReceive->removeElement($acheminementsReceive);
            // set the owning side to null (unless already changed)
            if ($acheminementsReceive->getReceiver() === $this) {
                $acheminementsReceive->setReceiver(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Acheminements[]
     */
    public function getAcheminementsRequester(): Collection
    {
        return $this->acheminementsRequester;
    }

    public function addAcheminementsRequester(Acheminements $acheminementsRequester): self
    {
        if (!$this->acheminementsRequester->contains($acheminementsRequester)) {
            $this->acheminementsRequester[] = $acheminementsRequester;
            $acheminementsRequester->setRequester($this);
        }

        return $this;
    }

    public function removeAcheminementsRequester(Acheminements $acheminementsRequester): self
    {
        if ($this->acheminementsRequester->contains($acheminementsRequester)) {
            $this->acheminementsRequester->removeElement($acheminementsRequester);
            // set the owning side to null (unless already changed)
            if ($acheminementsRequester->getRequester() === $this) {
                $acheminementsRequester->setRequester(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReceptionTraca[]
     */
    public function getReceptionsTraca(): Collection
    {
        return $this->receptionsTraca;
    }

    public function addReceptionsTraca(ReceptionTraca $receptionsTraca): self
    {
        if (!$this->receptionsTraca->contains($receptionsTraca)) {
            $this->receptionsTraca[] = $receptionsTraca;
            $receptionsTraca->setUser($this);
        }

        return $this;
    }

    public function removeReceptionsTraca(ReceptionTraca $receptionsTraca): self
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
            $litige->addBuyer($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            $litige->removeBuyer($this);
        }

        return $this;
    }

    public function getRechercheForArticle()
    {
        return $this->rechercheForArticle;
    }

    public function setRechercheForArticle($rechercheForArticle): self
    {
        $this->rechercheForArticle = $rechercheForArticle;

        return $this;
    }

    public function getColumnsVisibleForArticle()
    {
        return $this->columnsVisibleForArticle;
    }

    public function setColumnsVisibleForArticle($columnsVisibleForArticle): self
    {
        $this->columnsVisibleForArticle = $columnsVisibleForArticle;

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

    /**
     * @param mixed $columnsVisibleForLitige
     */
    public function setColumnsVisibleForLitige($columnsVisibleForLitige): void
    {
        $this->columnsVisibleForLitige = $columnsVisibleForLitige;
    }

    /**
     * @return mixed
     */
    public function getColumnsVisibleForLitige()
    {
        return $this->columnsVisibleForLitige;
    }

    /**
     * @param mixed $columnsVisibleForArrivage
     */
    public function setColumnsVisibleForArrivage($columnsVisibleForArrivage): void
    {
        $this->columnsVisibleForArrivage = $columnsVisibleForArrivage;
    }

    /**
     * @return mixed
     */
    public function getColumnsVisibleForArrivage()
    {
        return $this->columnsVisibleForArrivage;
    }

}
