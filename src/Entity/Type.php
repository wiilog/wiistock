<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use App\Entity\IOT\Sensor;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TypeRepository")
 */
class Type
{
    // types de la catégorie article
	// CEA
    const LABEL_CSP = 'CSP';
    const LABEL_PDT = 'PDT';
    const LABEL_SILI = 'SILI';
    const LABEL_SILICIUM = 'SILICIUM';
    const LABEL_SILI_EXT = 'SILI-ext';
    const LABEL_SILI_INT = 'SILI-int';
    const LABEL_MOB = 'MOB';
    const LABEL_SLUGCIBLE = 'SLUGCIBLE';
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
    const LABEL_SENSOR = 'capteur';


	/**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="FreeField", mappedBy="type")
     */
    private $champsLibres;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="type")
     */
    private $referenceArticles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="type")
     */
    private $articles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoryType", inversedBy="types")
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="type")
     */
    private $receptions;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="type")
	 */
	private $demandesLivraison;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Litige", mappedBy="type")
     */
    private $litiges;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collecte", mappedBy="type")
     */
    private $collectes;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Utilisateur", mappedBy="deliveryTypes")
     */
    private $deliveryUsers;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Utilisateur", mappedBy="dispatchTypes")
     */
    private $dispatchUsers;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Utilisateur", mappedBy="handlingTypes")
     */
    private $handlingUsers;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $sendMail;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Dispatch", mappedBy="type")
     */
    private $dispatches;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="type")
     */
    private $arrivals;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Statut", mappedBy="type", orphanRemoval=true)
     */
    private $statuts;

    /**
     * @ORM\OneToMany(targetEntity=Handling::class, mappedBy="type")
     */
    private $handlings;

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class, inversedBy="dropTypes")
     */
    private $dropLocation;

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class, inversedBy="pickTypes")
     */
    private $pickLocation;

    /**
     * @ORM\OneToOne(targetEntity=AverageRequestTime::class, mappedBy="type", cascade={"persist", "remove"})
     */
    private $averageRequestTime;

    /**
     * @ORM\OneToMany(targetEntity=Sensor::class, mappedBy="type")
     */
    private $sensors;

    /**
     * @ORM\OneToMany(targetEntity=AlertTemplate::class, mappedBy="type")
     */
    private $alertTemplates;

    /**
     * @ORM\OneToMany(targetEntity=DeliveryRequestTemplate::class, mappedBy="type")
     */
    private $deliveryRequestTemplates;

    /**
     * @ORM\OneToMany(targetEntity=HandlingRequestTemplate::class, mappedBy="type")
     */
    private $handlingRequestTemplates;

    /**
     * @ORM\OneToMany(targetEntity=CollectRequestTemplate::class, mappedBy="type")
     */
    private $collectRequestTemplates;

    /**
     * @ORM\OneToMany(targetEntity=DeliveryRequestTemplate::class, mappedBy="requestType")
     */
    private $deliveryRequestTypeTemplates;

    /**
     * @ORM\OneToMany(targetEntity=CollectRequestTemplate::class, mappedBy="requestType")
     */
    private $collectRequestTypeTemplates;

    /**
     * @ORM\OneToMany(targetEntity=HandlingRequestTemplate::class, mappedBy="requestType")
     */
    private $handlingRequestTypeTemplates;

    public function __construct()
    {
        $this->champsLibres = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->receptions = new ArrayCollection();
        $this->litiges = new ArrayCollection();
        $this->demandesLivraison = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->deliveryUsers = new ArrayCollection();
        $this->dispatchUsers = new ArrayCollection();
        $this->handlingUsers = new ArrayCollection();
        $this->dispatches = new ArrayCollection();
        $this->arrivals = new ArrayCollection();
        $this->statuts = new ArrayCollection();
        $this->handlings = new ArrayCollection();
        $this->sensors = new ArrayCollection();
        $this->alertTemplates = new ArrayCollection();
        $this->deliveryRequestTemplates = new ArrayCollection();
        $this->handlingRequestTemplates = new ArrayCollection();
        $this->collectRequestTemplates = new ArrayCollection();
        $this->deliveryRequestTypeTemplates = new ArrayCollection();
        $this->collectRequestTypeTemplates = new ArrayCollection();
        $this->handlingRequestTypeTemplates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|FreeField[]
     */
    public function getChampsLibres(): Collection
    {
        return $this->champsLibres;
    }

    public function addChampLibre(FreeField $champLibre): self
    {
        if (!$this->champsLibres->contains($champLibre)) {
            $this->champsLibres[] = $champLibre;
            $champLibre->setType($this);
        }

        return $this;
    }

    public function removeChampLibre(FreeField $champLibre): self
    {
        if ($this->champsLibres->contains($champLibre)) {
            $this->champsLibres->removeElement($champLibre);
            // set the owning side to null (unless already changed)
            if ($champLibre->getType() === $this) {
                $champLibre->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferenceArticles(): Collection
    {
        return $this->referenceArticles;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if (!$this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles[] = $referenceArticle;
            $referenceArticle->setType($this);
        }

        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if ($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getType() === $this) {
                $referenceArticle->setType(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?CategoryType
    {
        return $this->category;
    }

    public function setCategory(?CategoryType $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setType($this);
        }

        return $this;
    }
    public function removeArticle(Article $article): self
    {
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
    public function getReceptions(): Collection
    {
        return $this->receptions;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setType($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
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
     * @return Collection|Litige[]
     */
    public function getLitiges(): Collection
    {
        return $this->litiges;
    }

    public function addCommentaire(Litige $commentaire): self
    {
        if (!$this->litiges->contains($commentaire)) {
            $this->litiges[] = $commentaire;
            $commentaire->setType($this);
        }

        return $this;
    }

    public function removeCommentaire(Litige $commentaire): self
    {
        if ($this->litiges->contains($commentaire)) {
            $this->litiges->removeElement($commentaire);
            // set the owning side to null (unless already changed)
            if ($commentaire->getType() === $this) {
                $commentaire->setType(null);
            }
        }

        return $this;
    }

    public function addLitige(Litige $litige): self
    {
        if (!$this->litiges->contains($litige)) {
            $this->litiges[] = $litige;
            $litige->setType($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            // set the owning side to null (unless already changed)
            if ($litige->getType() === $this) {
                $litige->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Demande[]
     */
    public function getDemandesLivraison(): Collection
    {
        return $this->demandesLivraison;
    }

    public function addDemandesLivraison(Demande $demandesLivraison): self
    {
        if (!$this->demandesLivraison->contains($demandesLivraison)) {
            $this->demandesLivraison[] = $demandesLivraison;
            $demandesLivraison->setType($this);
        }

        return $this;
    }

    public function removeDemandesLivraison(Demande $demandesLivraison): self
    {
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
    public function getCollectes(): Collection
    {
        return $this->collectes;
    }

    public function addCollecte(Collecte $collecte): self
    {
        if (!$this->collectes->contains($collecte)) {
            $this->collectes[] = $collecte;
            $collecte->setType($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self
    {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if ($collecte->getType() === $this) {
                $collecte->setType(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function addChampsLibre(FreeField $champsLibre): self
    {
        if (!$this->champsLibres->contains($champsLibre)) {
            $this->champsLibres[] = $champsLibre;
            $champsLibre->setType($this);
        }

        return $this;
    }

    public function removeChampsLibre(FreeField $champsLibre): self
    {
        if ($this->champsLibres->contains($champsLibre)) {
            $this->champsLibres->removeElement($champsLibre);
            // set the owning side to null (unless already changed)
            if ($champsLibre->getType() === $this) {
                $champsLibre->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getDeliveryUsers(): Collection
    {
        return $this->deliveryUsers;
    }

    public function addDeliveryUser(Utilisateur $user): self
    {
        if (!$this->deliveryUsers->contains($user)) {
            $this->deliveryUsers[] = $user;
            $user->addDeliveryType($this);
        }

        return $this;
    }

    public function removeDeliveryUser(Utilisateur $user): self
    {
        if ($this->deliveryUsers->contains($user)) {
            $this->deliveryUsers->removeElement($user);
            $user->removeDeliveryType($this);
        }

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getDispatchUsers(): Collection
    {
        return $this->dispatchUsers;
    }

    public function addDispatchUser(Utilisateur $user): self
    {
        if (!$this->dispatchUsers->contains($user)) {
            $this->dispatchUsers[] = $user;
            $user->addDispatchType($this);
        }

        return $this;
    }

    public function removeDispatchUser(Utilisateur $user): self
    {
        if ($this->dispatchUsers->contains($user)) {
            $this->dispatchUsers->removeElement($user);
            $user->removeDispatchType($this);
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getHandlingUsers(): Collection
    {
        return $this->handlingUsers;
    }

    public function addHandlingUser(Utilisateur $user): self
    {
        if (!$this->handlingUsers->contains($user)) {
            $this->handlingUsers[] = $user;
            $user->addHandlingType($this);
        }

        return $this;
    }

    public function removeHandlingUser(Utilisateur $user): self
    {
        if ($this->handlingUsers->contains($user)) {
            $this->handlingUsers->removeElement($user);
            $user->removeHandlingType($this);
        }

        return $this;
    }

    public function getSendMail(): ?bool
    {
        return $this->sendMail;
    }

    public function setSendMail(?bool $sendMail): self
    {
        $this->sendMail = $sendMail;

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getDispatches(): Collection
    {
        return $this->dispatches;
    }

    public function addDispatch(Dispatch $dispatch): self
    {
        if (!$this->dispatches->contains($dispatch)) {
            $this->dispatches[] = $dispatch;
            $dispatch->setType($this);
        }

        return $this;
    }

    public function removeDispatch(Dispatch $dispatch): self
    {
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
    public function getArrivals(): Collection
    {
        return $this->arrivals;
    }

    public function addArrival(Arrivage $arrival): self
    {
        if (!$this->arrivals->contains($arrival)) {
            $this->arrivals[] = $arrival;
            $arrival->setType($this);
        }

        return $this;
    }

    public function removeArrival(Arrivage $arrival): self
    {
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
    public function getStatuts(): Collection
    {
        return $this->statuts;
    }

    public function addStatut(Statut $statut): self
    {
        if (!$this->statuts->contains($statut)) {
            $this->statuts[] = $statut;
            $statut->setType($this);
        }

        return $this;
    }

    public function removeStatut(Statut $statut): self
    {
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
    public function getHandlings(): Collection
    {
        return $this->handlings;
    }

    public function addHandling(Handling $handling): self
    {
        if (!$this->handlings->contains($handling)) {
            $this->handlings[] = $handling;
            $handling->setType($this);
        }

        return $this;
    }

    public function removeHandling(Handling $handling): self
    {
        if ($this->handlings->contains($handling)) {
            $this->handlings->removeElement($handling);
            // set the owning side to null (unless already changed)
            if ($handling->getType() === $this) {
                $handling->setType(null);
            }
        }

        return $this;
    }

    public function getDropLocation(): ?Emplacement
    {
        return $this->dropLocation;
    }

    public function setDropLocation(?Emplacement $dropLocation): self
    {
        $this->dropLocation = $dropLocation;

        return $this;
    }

    public function getPickLocation(): ?Emplacement
    {
        return $this->pickLocation;
    }

    public function setPickLocation(?Emplacement $pickLocation): self
    {
        $this->pickLocation = $pickLocation;

        return $this;
    }

    public function getAverageRequestTime(): ?AverageRequestTime
    {
        return $this->averageRequestTime;
    }

    public function setAverageRequestTime(AverageRequestTime $averageRequestTime): self
    {
        $this->averageRequestTime = $averageRequestTime;

        // set the owning side of the relation if necessary
        if ($averageRequestTime->getType() !== $this) {
            $averageRequestTime->setType($this);
        }

        return $this;
    }

    /**
     * @return Collection|Sensor[]
     */
    public function getSensors(): Collection
    {
        return $this->sensors;
    }

    public function addSensor(Sensor $sensor): self
    {
        if (!$this->sensors->contains($sensor)) {
            $this->sensors[] = $sensor;
            $sensor->setType($this);
        }

        return $this;
    }

    public function removeSensor(Sensor $sensor): self
    {
        if ($this->sensors->removeElement($sensor)) {
            // set the owning side to null (unless already changed)
            if ($sensor->getType() === $this) {
                $sensor->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|AlertTemplate[]
     */
    public function getAlertTemplates(): Collection
    {
        return $this->alertTemplates;
    }

    public function addAlertTemplate(AlertTemplate $alertTemplate): self
    {
        if (!$this->alertTemplates->contains($alertTemplate)) {
            $this->alertTemplates[] = $alertTemplate;
            $alertTemplate->setType($this);
        }

        return $this;
    }

    public function removeAlertTemplate(AlertTemplate $alertTemplate): self
    {
        if ($this->alertTemplates->removeElement($alertTemplate)) {
            // set the owning side to null (unless already changed)
            if ($alertTemplate->getType() === $this) {
                $alertTemplate->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DeliveryRequestTemplate[]
     */
    public function getDeliveryRequestTemplates(): Collection
    {
        return $this->deliveryRequestTemplates;
    }

    public function addDeliveryRequestTemplate(DeliveryRequestTemplate $deliveryRequestTemplate): self
    {
        if (!$this->deliveryRequestTemplates->contains($deliveryRequestTemplate)) {
            $this->deliveryRequestTemplates[] = $deliveryRequestTemplate;
            $deliveryRequestTemplate->setType($this);
        }

        return $this;
    }

    public function removeDeliveryRequestTemplate(DeliveryRequestTemplate $deliveryRequestTemplate): self
    {
        if ($this->deliveryRequestTemplates->removeElement($deliveryRequestTemplate)) {
            // set the owning side to null (unless already changed)
            if ($deliveryRequestTemplate->getType() === $this) {
                $deliveryRequestTemplate->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|HandlingRequestTemplate[]
     */
    public function getHandlingRequestTemplates(): Collection
    {
        return $this->handlingRequestTemplates;
    }

    public function addHandlingRequestTemplate(HandlingRequestTemplate $handlingRequestTemplate): self
    {
        if (!$this->handlingRequestTemplates->contains($handlingRequestTemplate)) {
            $this->handlingRequestTemplates[] = $handlingRequestTemplate;
            $handlingRequestTemplate->setType($this);
        }

        return $this;
    }

    public function removeHandlingRequestTemplate(HandlingRequestTemplate $handlingRequestTemplate): self
    {
        if ($this->handlingRequestTemplates->removeElement($handlingRequestTemplate)) {
            // set the owning side to null (unless already changed)
            if ($handlingRequestTemplate->getType() === $this) {
                $handlingRequestTemplate->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|CollectRequestTemplate[]
     */
    public function getCollectRequestTemplates(): Collection
    {
        return $this->collectRequestTemplates;
    }

    public function addCollectRequestTemplate(CollectRequestTemplate $collectRequestTemplate): self
    {
        if (!$this->collectRequestTemplates->contains($collectRequestTemplate)) {
            $this->collectRequestTemplates[] = $collectRequestTemplate;
            $collectRequestTemplate->setType($this);
        }

        return $this;
    }

    public function removeCollectRequestTemplate(CollectRequestTemplate $collectRequestTemplate): self
    {
        if ($this->collectRequestTemplates->removeElement($collectRequestTemplate)) {
            // set the owning side to null (unless already changed)
            if ($collectRequestTemplate->getType() === $this) {
                $collectRequestTemplate->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DeliveryRequestTemplate[]
     */
    public function getDeliveryRequestTypeTemplates(): Collection
    {
        return $this->deliveryRequestTypeTemplates;
    }

    public function addDeliveryRequestTypeTemplate(DeliveryRequestTemplate $deliveryRequestTypeTemplate): self
    {
        if (!$this->deliveryRequestTypeTemplates->contains($deliveryRequestTypeTemplate)) {
            $this->deliveryRequestTypeTemplates[] = $deliveryRequestTypeTemplate;
            $deliveryRequestTypeTemplate->setRequestType($this);
        }

        return $this;
    }

    public function removeDeliveryRequestTypeTemplate(DeliveryRequestTemplate $deliveryRequestTypeTemplate): self
    {
        if ($this->deliveryRequestTypeTemplates->removeElement($deliveryRequestTypeTemplate)) {
            // set the owning side to null (unless already changed)
            if ($deliveryRequestTypeTemplate->getRequestType() === $this) {
                $deliveryRequestTypeTemplate->setRequestType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|CollectRequestTemplate[]
     */
    public function getCollectRequestTypeTemplates(): Collection
    {
        return $this->collectRequestTypeTemplates;
    }

    public function addCollectRequestTypeTemplate(CollectRequestTemplate $collectRequestTypeTemplate): self
    {
        if (!$this->collectRequestTypeTemplates->contains($collectRequestTypeTemplate)) {
            $this->collectRequestTypeTemplates[] = $collectRequestTypeTemplate;
            $collectRequestTypeTemplate->setRequestType($this);
        }

        return $this;
    }

    public function removeCollectRequestTypeTemplate(CollectRequestTemplate $collectRequestTypeTemplate): self
    {
        if ($this->collectRequestTypeTemplates->removeElement($collectRequestTypeTemplate)) {
            // set the owning side to null (unless already changed)
            if ($collectRequestTypeTemplate->getRequestType() === $this) {
                $collectRequestTypeTemplate->setRequestType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|HandlingRequestTemplate[]
     */
    public function getHandlingRequestTypeTemplates(): Collection
    {
        return $this->handlingRequestTypeTemplates;
    }

    public function addHandlingRequestTypeTemplate(HandlingRequestTemplate $handlingRequestTypeTemplate): self
    {
        if (!$this->handlingRequestTypeTemplates->contains($handlingRequestTypeTemplate)) {
            $this->handlingRequestTypeTemplates[] = $handlingRequestTypeTemplate;
            $handlingRequestTypeTemplate->setRequestType($this);
        }

        return $this;
    }

    public function removeHandlingRequestTypeTemplate(HandlingRequestTemplate $handlingRequestTypeTemplate): self
    {
        if ($this->handlingRequestTypeTemplates->removeElement($handlingRequestTypeTemplate)) {
            // set the owning side to null (unless already changed)
            if ($handlingRequestTypeTemplate->getRequestType() === $this) {
                $handlingRequestTypeTemplate->setRequestType(null);
            }
        }

        return $this;
    }
}
