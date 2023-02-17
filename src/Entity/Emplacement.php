<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorMessageTrait;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\Vehicle;
use App\Repository\EmplacementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OneToMany;


#[ORM\Entity(repositoryClass: EmplacementRepository::class)]
class Emplacement implements PairedEntity {

    use SensorMessageTrait;

    const LABEL_A_DETERMINER = 'A DETERMINER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'destination', targetEntity: Livraison::class)]
    private Collection $livraisons;

    #[ORM\OneToMany(mappedBy: 'destination', targetEntity: 'App\Entity\DeliveryRequest\Demande')]
    private Collection $demandes;

    #[ORM\OneToMany(mappedBy: 'pointCollecte', targetEntity: Collecte::class)]
    private Collection $collectes;

    #[ORM\OneToMany(mappedBy: 'emplacement', targetEntity: Article::class)]
    private Collection $articles;

    #[ORM\OneToMany(mappedBy: 'emplacement', targetEntity: ReferenceArticle::class)]
    private Collection $referenceArticles;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isDeliveryPoint = null;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private ?bool $isOngoingVisibleOnMobile;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => true])]
    private ?bool $isActive;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $dateMaxTime = null;

    #[ORM\OneToMany(mappedBy: 'locationDropzone', targetEntity: Utilisateur::class)]
    private Collection $utilisateurs;

    #[ORM\ManyToMany(targetEntity: Nature::class, inversedBy: 'emplacements')]
    private Collection $allowedNatures;

    #[ORM\OneToMany(mappedBy: 'locationFrom', targetEntity: Dispatch::class)]
    private Collection $dispatchesFrom;

    #[ORM\OneToMany(mappedBy: 'locationTo', targetEntity: Dispatch::class)]
    private Collection $dispatchesTo;

    #[ORM\OneToMany(mappedBy: 'dropLocation', targetEntity: Type::class)]
    private Collection $dropTypes;

    #[ORM\OneToMany(mappedBy: 'pickLocation', targetEntity: Type::class)]
    private Collection $pickTypes;

    #[ORM\ManyToMany(targetEntity: LocationCluster::class, mappedBy: 'locations')]
    private Collection $clusters;

    #[ORM\OneToMany(mappedBy: 'dropLocation', targetEntity: Arrivage::class)]
    private Collection $arrivals;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'location_allowed_delivery_type')]
    private Collection $allowedDeliveryTypes;

    #[ORM\ManyToMany(targetEntity: Type::class)]
    #[ORM\JoinTable(name: 'location_allowed_collect_type')]
    private Collection $allowedCollectTypes;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: Pairing::class, cascade: ['remove'])]
    private Collection $pairings;

    #[ORM\OneToMany(mappedBy: 'destination', targetEntity: DeliveryRequestTemplate::class)]
    private Collection $deliveryRequestTemplates;

    #[ORM\OneToMany(mappedBy: 'collectPoint', targetEntity: CollectRequestTemplate::class)]
    private Collection $collectRequestTemplates;

    #[ORM\ManyToOne(targetEntity: LocationGroup::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?LocationGroup $locationGroup = null;

    #[ORM\OneToMany(mappedBy: 'targetLocationPicking', targetEntity: DeliveryRequest\DeliveryRequestArticleLine::class)]
    private Collection $deliveryRequestArticleLines;

    #[ORM\OneToMany(mappedBy: 'targetLocationPicking', targetEntity: DeliveryRequest\DeliveryRequestReferenceLine::class)]
    private Collection $deliveryRequestReferenceLines;

    #[ORM\OneToMany(mappedBy: 'targetLocationPicking', targetEntity: PreparationOrder\PreparationOrderArticleLine::class)]
    private Collection $preparationOrderArticleLines;

    #[ORM\OneToMany(mappedBy: 'targetLocationPicking', targetEntity: PreparationOrder\PreparationOrderReferenceLine::class)]
    private Collection $preparationOrderReferenceLines;

    #[ORM\OneToMany(mappedBy: 'dropLocation', targetEntity: TransferOrder::class)]
    private Collection $transferOrders;

    #[ORM\ManyToMany(targetEntity: TemperatureRange::class, inversedBy: 'locations')]
    private Collection $temperatureRanges;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $signatories;

    #[OneToMany(mappedBy: "location", targetEntity: InventoryLocationMission::class)]
    private Collection $inventoryLocationMissions;

    #[ORM\ManyToOne(targetEntity: Zone::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Zone $zone = null;

    #[ORM\ManyToMany(targetEntity: InventoryMissionRule::class, mappedBy: 'locations')]
    private Collection $inventoryMissionRules;

    public function __construct() {
        $this->clusters = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->utilisateurs = new ArrayCollection();
        $this->allowedNatures = new ArrayCollection();
        $this->dispatchesFrom = new ArrayCollection();
        $this->dispatchesTo = new ArrayCollection();
        $this->dropTypes = new ArrayCollection();
        $this->pickTypes = new ArrayCollection();
        $this->arrivals = new ArrayCollection();
        $this->allowedDeliveryTypes = new ArrayCollection();
        $this->allowedCollectTypes = new ArrayCollection();
        $this->pairings = new ArrayCollection();
        $this->deliveryRequestTemplates = new ArrayCollection();
        $this->collectRequestTemplates = new ArrayCollection();
        $this->sensorMessages = new ArrayCollection();
        $this->deliveryRequestArticleLines = new ArrayCollection();
        $this->deliveryRequestReferenceLines = new ArrayCollection();
        $this->preparationOrderArticleLines = new ArrayCollection();
        $this->transferOrders = new ArrayCollection();
        $this->signatories = new ArrayCollection();
        $this->temperatureRanges = new ArrayCollection();
        $this->inventoryMissionRules = new ArrayCollection();

        $this->isOngoingVisibleOnMobile = false;
        $this->isActive = true;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function __toString() {
        return $this->label;
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
            $livraison->setDestination($this);
        }

        return $this;
    }

    public function removeLivraison(Livraison $livraison): self {
        if($this->livraisons->contains($livraison)) {
            $this->livraisons->removeElement($livraison);
            // set the owning side to null (unless already changed)
            if($livraison->getDestination() === $this) {
                $livraison->setDestination(null);
            }
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
            $demande->setDestination($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self {
        if($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if($demande->getDestination() === $this) {
                $demande->setDestination(null);
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
     * @return Collection|Collecte[]
     */
    public function getCollectes(): Collection {
        return $this->collectes;
    }

    public function addCollecte(Collecte $collecte): self {
        if(!$this->collectes->contains($collecte)) {
            $this->collectes[] = $collecte;
            $collecte->setPointCollecte($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self {
        if($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if($collecte->getPointCollecte() === $this) {
                $collecte->setPointCollecte(null);
            }
        }

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
            $article->setEmplacement($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        if($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if($article->getEmplacement() === $this) {
                $article->setEmplacement(null);
            }
        }

        return $this;
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
            $referenceArticle->setEmplacement($this);
        }

        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self {
        if($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if($referenceArticle->getEmplacement() === $this) {
                $referenceArticle->setEmplacement(null);
            }
        }

        return $this;
    }

    public function getIsDeliveryPoint(): ?bool {
        return $this->isDeliveryPoint;
    }

    public function setIsDeliveryPoint(?bool $isDeliveryPoint): self {
        $this->isDeliveryPoint = $isDeliveryPoint;

        return $this;
    }

    public function isOngoingVisibleOnMobile(): ?bool {
        return $this->isOngoingVisibleOnMobile;
    }

    public function setIsOngoingVisibleOnMobile(?bool $isOngoingVisibleOnMobile): self {
        $this->isOngoingVisibleOnMobile = $isOngoingVisibleOnMobile;
        return $this;
    }

    public function getIsActive(): ?bool {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self {
        $this->isActive = $isActive;

        return $this;
    }

    public function getDateMaxTime(): ?string {
        return $this->dateMaxTime;
    }

    public function setDateMaxTime(?string $dateMaxTime): self {
        $this->dateMaxTime = $dateMaxTime;

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getUtilisateurs(): Collection {
        return $this->utilisateurs;
    }

    public function addUtilisateur(Utilisateur $utilisateur): self {
        if(!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs[] = $utilisateur;
            $utilisateur->setLocationDropzone($this);
        }

        return $this;
    }

    public function removeUtilisateur(Utilisateur $utilisateur): self {
        if($this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs->removeElement($utilisateur);
            // set the owning side to null (unless already changed)
            if($utilisateur->getLocationDropzone() === $this) {
                $utilisateur->setLocationDropzone(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Nature[]
     */
    public function getAllowedNatures(): Collection {
        return $this->allowedNatures;
    }

    public function addAllowedNature(Nature $allowedNature): self {
        if(!$this->allowedNatures->contains($allowedNature)) {
            $this->allowedNatures[] = $allowedNature;
        }

        return $this;
    }

    public function removeAllowedNature(Nature $allowedNature): self {
        if($this->allowedNatures->contains($allowedNature)) {
            $this->allowedNatures->removeElement($allowedNature);
        }

        return $this;
    }

    public function setAllowedNatures(?array $allowedNatures): self {
        foreach($this->getAllowedNatures()->toArray() as $nature) {
            $this->removeAllowedNature($nature);
        }
        $this->allowedNatures = new ArrayCollection();
        foreach($allowedNatures as $allowedNature) {
            $this->addAllowedNature($allowedNature);
        }
        return $this;
    }

    public function ableToBeDropOff(?Pack $pack): bool {
        return (
            $this->getAllowedNatures()->isEmpty()
            || (
                $pack
                && $pack->getNature()
                && $this->getAllowedNatures()->contains($pack->getNature())
            )
        );
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getDispatchesFrom(): Collection {
        return $this->dispatchesFrom;
    }

    public function addDispatchFrom(Dispatch $dispatchFrom): self {
        if(!$this->dispatchesFrom->contains($dispatchFrom)) {
            $this->dispatchesFrom[] = $dispatchFrom;
            $dispatchFrom->setLocationFrom($this);
        }

        return $this;
    }

    public function removeDispatchFrom(Dispatch $dispatchFrom): self {
        if($this->dispatchesFrom->contains($dispatchFrom)) {
            $this->dispatchesFrom->removeElement($dispatchFrom);
            // set the owning side to null (unless already changed)
            if($dispatchFrom->getLocationFrom() === $this) {
                $dispatchFrom->setLocationFrom(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getDispatchesTo(): Collection {
        return $this->dispatchesTo;
    }

    public function addDispatchTo(Dispatch $dispatchTo): self {
        if(!$this->dispatchesTo->contains($dispatchTo)) {
            $this->dispatchesTo[] = $dispatchTo;
            $dispatchTo->setLocationTo($this);
        }

        return $this;
    }

    public function removeDispatchTo(Dispatch $dispatchTo): self {
        if($this->dispatchesTo->contains($dispatchTo)) {
            $this->dispatchesTo->removeElement($dispatchTo);
            // set the owning side to null (unless already changed)
            if($dispatchTo->getLocationTo() === $this) {
                $dispatchTo->setLocationTo(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Type[]
     */
    public function getDropTypes(): Collection {
        return $this->dropTypes;
    }

    public function addDropType(Type $dropType): self {
        if(!$this->dropTypes->contains($dropType)) {
            $this->dropTypes[] = $dropType;
            $dropType->setDropLocation($this);
        }

        return $this;
    }

    public function removeDropType(Type $dropType): self {
        if($this->dropTypes->contains($dropType)) {
            $this->dropTypes->removeElement($dropType);
            // set the owning side to null (unless already changed)
            if($dropType->getDropLocation() === $this) {
                $dropType->setDropLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Type[]
     */
    public function getPickTypes(): Collection {
        return $this->pickTypes;
    }

    public function addPickType(Type $pickType): self {
        if(!$this->pickTypes->contains($pickType)) {
            $this->pickTypes[] = $pickType;
            $pickType->setPickLocation($this);
        }

        return $this;
    }

    public function removePickType(Type $pickType): self {
        if($this->pickTypes->contains($pickType)) {
            $this->pickTypes->removeElement($pickType);
            // set the owning side to null (unless already changed)
            if($pickType->getPickLocation() === $this) {
                $pickType->setPickLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getClusters(): Collection {
        return $this->clusters;
    }

    /**
     * @param LocationCluster $locationCluster
     * @return Emplacement
     */
    public function addCluster(LocationCluster $locationCluster): self {
        if(!$this->clusters->contains($locationCluster)) {
            $this->clusters->add($locationCluster);
            $locationCluster->addLocation($this);
        }
        return $this;
    }

    /**
     * @param LocationCluster $locationCluster
     * @return Emplacement
     */
    public function removeCluster(LocationCluster $locationCluster): self {
        if($this->clusters->contains($locationCluster)) {
            $this->clusters->removeElement($locationCluster);
            $locationCluster->removeLocation($this);
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
        if(!$this->arrivals->contains($arrival)) {
            $this->arrivals[] = $arrival;
            $arrival->setDropLocation($this);
        }

        return $this;
    }

    public function removeArrival(Arrivage $arrival): self {
        if($this->arrivals->contains($arrival)) {
            $this->arrivals->removeElement($arrival);
            // set the owning side to null (unless already changed)
            if($arrival->getDropLocation() === $this) {
                $arrival->setDropLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Type[]
     */
    public function getAllowedDeliveryTypes(): Collection {
        return $this->allowedDeliveryTypes;
    }

    public function addAllowedDeliveryType(Type $allowedDeliveryType): self {
        if(!$this->allowedDeliveryTypes->contains($allowedDeliveryType)) {
            $this->allowedDeliveryTypes[] = $allowedDeliveryType;
        }

        return $this;
    }

    public function removeAllowedDeliveryType(Type $allowedDeliveryType): self {
        $this->allowedDeliveryTypes->removeElement($allowedDeliveryType);

        return $this;
    }

    public function setAllowedDeliveryTypes(?array $allowedDeliveryTypes): self {
        foreach($this->getAllowedDeliveryTypes()->toArray() as $allowedDeliveryType) {
            $this->removeAllowedDeliveryType($allowedDeliveryType);
        }

        $this->allowedDeliveryTypes = new ArrayCollection();
        foreach($allowedDeliveryTypes as $allowedDeliveryType) {
            $this->addAllowedDeliveryType($allowedDeliveryType);
        }

        return $this;
    }

    /**
     * @return Collection|Type[]
     */
    public function getAllowedCollectTypes(): Collection {
        return $this->allowedCollectTypes;
    }

    public function addAllowedCollectType(Type $allowedCollectType): self {
        if(!$this->allowedCollectTypes->contains($allowedCollectType)) {
            $this->allowedCollectTypes[] = $allowedCollectType;
        }

        return $this;
    }

    public function removeAllowedCollectType(Type $allowedCollectType): self {
        $this->allowedCollectTypes->removeElement($allowedCollectType);

        return $this;
    }

    public function setAllowedCollectTypes(?array $allowedCollectTypes): self {
        foreach($this->getAllowedCollectTypes()->toArray() as $allowedCollectType) {
            $this->removeAllowedCollectType($allowedCollectType);
        }

        $this->allowedCollectTypes = new ArrayCollection();
        foreach($allowedCollectTypes as $allowedCollectType) {
            $this->addAllowedCollectType($allowedCollectType);
        }

        return $this;
    }

    public function getLocationGroup(): ?LocationGroup {
        return $this->locationGroup;
    }

    public function setLocationGroup(?LocationGroup $locationGroup): self {
        if($this->locationGroup && $this->locationGroup !== $locationGroup) {
            $this->locationGroup->removeLocation($this);
        }

        $this->locationGroup = $locationGroup;

        if($locationGroup) {
            $locationGroup->addLocation($this);
        }

        return $this;
    }

    /**
     * @return Collection|Pairing[]
     */
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
            $pairing->setLocation($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self {
        if($this->pairings->removeElement($pairing)) {
            // set the owning side to null (unless already changed)
            if($pairing->getLocation() === $this) {
                $pairing->setLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DeliveryRequestTemplate[]
     */
    public function getDeliveryRequestTemplates(): Collection {
        return $this->deliveryRequestTemplates;
    }

    public function addDeliveryRequestTemplate(DeliveryRequestTemplate $deliveryRequestTemplate): self {
        if(!$this->deliveryRequestTemplates->contains($deliveryRequestTemplate)) {
            $this->deliveryRequestTemplates[] = $deliveryRequestTemplate;
            $deliveryRequestTemplate->setDestination($this);
        }

        return $this;
    }

    public function removeDeliveryRequestTemplate(DeliveryRequestTemplate $deliveryRequestTemplate): self {
        if($this->deliveryRequestTemplates->removeElement($deliveryRequestTemplate)) {
            // set the owning side to null (unless already changed)
            if($deliveryRequestTemplate->getDestination() === $this) {
                $deliveryRequestTemplate->setDestination(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|CollectRequestTemplate[]
     */
    public function getCollectRequestTemplates(): Collection {
        return $this->collectRequestTemplates;
    }

    public function addCollectRequestTemplate(CollectRequestTemplate $collectRequestTemplate): self {
        if(!$this->collectRequestTemplates->contains($collectRequestTemplate)) {
            $this->collectRequestTemplates[] = $collectRequestTemplate;
            $collectRequestTemplate->setCollectPoint($this);
        }

        return $this;
    }

    public function removeCollectRequestTemplate(CollectRequestTemplate $collectRequestTemplate): self {
        if($this->collectRequestTemplates->removeElement($collectRequestTemplate)) {
            // set the owning side to null (unless already changed)
            if($collectRequestTemplate->getCollectPoint() === $this) {
                $collectRequestTemplate->setCollectPoint(null);
            }
        }

        return $this;
    }

    public function getDeliveryRequestArticleLines(): Collection {
        return $this->deliveryRequestArticleLines;
    }

    public function addDeliveryRequestArticleLine(DeliveryRequestArticleLine $deliveryRequestArticleLine): self {
        if(!$this->deliveryRequestArticleLines->contains($deliveryRequestArticleLine)) {
            $this->deliveryRequestArticleLines[] = $deliveryRequestArticleLine;
            $deliveryRequestArticleLine->setTargetLocationPicking($this);
        }

        return $this;
    }

    public function removeDeliveryRequestArticleLine(DeliveryRequestArticleLine $deliveryRequestArticleLine): self {
        if($this->deliveryRequestArticleLines->removeElement($deliveryRequestArticleLine)) {
            // set the owning side to null (unless already changed)
            if($deliveryRequestArticleLine->getTargetLocationPicking() === $this) {
                $deliveryRequestArticleLine->setTargetLocationPicking(null);
            }
        }

        return $this;
    }

    public function getDeliveryRequestReferenceLines(): Collection {
        return $this->deliveryRequestArticleLines;
    }

    public function addDeliveryRequestReferenceLine(DeliveryRequestReferenceLine $deliveryRequestReferenceLine): self {
        if(!$this->deliveryRequestReferenceLines->contains($deliveryRequestReferenceLine)) {
            $this->deliveryRequestReferenceLines[] = $deliveryRequestReferenceLine;
            $deliveryRequestReferenceLine->setTargetLocationPicking($this);
        }

        return $this;
    }

    public function removeDeliveryRequestReferenceLine(DeliveryRequestReferenceLine $deliveryRequestReferenceLine): self {
        if($this->deliveryRequestReferenceLines->removeElement($deliveryRequestReferenceLine)) {
            // set the owning side to null (unless already changed)
            if($deliveryRequestReferenceLine->getTargetLocationPicking() === $this) {
                $deliveryRequestReferenceLine->setTargetLocationPicking(null);
            }
        }

        return $this;
    }

    public function getPreparationOrderArticleLines(): Collection {
        return $this->preparationOrderArticleLines;
    }

    public function addPreparationOrderArticleLine(PreparationOrderArticleLine $preparationOrderArticleLine): self {
        if(!$this->preparationOrderArticleLines->contains($preparationOrderArticleLine)) {
            $this->preparationOrderArticleLines[] = $preparationOrderArticleLine;
            $preparationOrderArticleLine->setTargetLocationPicking($this);
        }

        return $this;
    }

    public function removePreparationOrderArticleLine(PreparationOrderArticleLine $preparationOrderArticleLine): self {
        if($this->preparationOrderArticleLines->removeElement($preparationOrderArticleLine)) {
            // set the owning side to null (unless already changed)
            if($preparationOrderArticleLine->getTargetLocationPicking() === $this) {
                $preparationOrderArticleLine->setTargetLocationPicking(null);
            }
        }

        return $this;
    }

    public function getPreparationOrderReferenceLines(): Collection {
        return $this->preparationOrderReferenceLines;
    }

    public function addPreparationOrderReferenceLine(PreparationOrderReferenceLine $preparationOrderReferenceLine): self {
        if(!$this->preparationOrderReferenceLines->contains($preparationOrderReferenceLine)) {
            $this->preparationOrderReferenceLines[] = $preparationOrderReferenceLine;
            $preparationOrderReferenceLine->setTargetLocationPicking($this);
        }

        return $this;
    }

    public function removePreparationOrderReferenceLine(PreparationOrderReferenceLine $preparationOrderReferenceLine): self {
        if($this->preparationOrderReferenceLines->removeElement($preparationOrderReferenceLine)) {
            // set the owning side to null (unless already changed)
            if($preparationOrderReferenceLine->getTargetLocationPicking() === $this) {
                $preparationOrderReferenceLine->setTargetLocationPicking(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|TransferOrder []
     */
    public function getTransferOrders(): Collection {
        return $this->transferOrders;
    }

    public function addTransferOrder(TransferOrder $transferOrder): self {
        if(!$this->transferOrders->contains($transferOrder)) {
            $this->transferOrders[] = $transferOrder;
            $transferOrder->setDropLocation($this);
        }

        return $this;
    }

    public function removeTransferOrder(TransferOrder $transferOrder): self {
        if($this->transferOrders->removeElement($transferOrder)) {
            if($transferOrder->getDropLocation() === $this) {
                $transferOrder->setDropLocation(null);
            }
        }

        return $this;
    }

    public function setTransferOrder(?array $transferOrders): self {
        foreach($this->gettransferOrders()->toArray() as $transferOrder) {
            $this->removeTransferOrder($transferOrder);
        }

        $this->transferOrders = new ArrayCollection();
        foreach($transferOrders as $transferOrder) {
            $this->addTransferOrder($transferOrder);
        }

        return $this;
    }

    /**
     * @return Collection<int, TemperatureRange>
     */
    public function getTemperatureRanges(): Collection
    {
        return $this->temperatureRanges;
    }

    public function addTemperatureRange(TemperatureRange $temperatureRange): self {
        if (!$this->temperatureRanges->contains($temperatureRange)) {
            $this->temperatureRanges[] = $temperatureRange;
            $temperatureRange->addLocation($this);
        }

        return $this;
    }

    public function removeTemperatureRange(TemperatureRange $temperatureRange): self {
        if ($this->temperatureRanges->removeElement($temperatureRange)) {
            $temperatureRange->removeLocation($this);
        }

        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): self {
        if($this->vehicle && $this->vehicle !== $vehicle) {
            $this->vehicle->removeLocation($this);
        }
        $this->vehicle = $vehicle;
        $vehicle?->addLocation($this);

        return $this;
    }

    public function getEmail(): ?string {
        return $this->email;
    }

    public function setEmail(?string $email): self {
        $this->email = $email;
        return $this;
    }

    public function getSignatories(): Collection {
        return $this->signatories;
    }

    public function addSignatory(Utilisateur $signatory): self {

        if (!$this->signatories->contains($signatory)) {
            $this->signatories->add($signatory);
        }

        return $this;
    }

    public function removeSignatory(Utilisateur $signatory): self {
        $this->signatories->removeElement($signatory);

        return $this;
    }

    /**
     * @param Utilisateur[] $signatories
     */
    public function setSignatories(array $signatories): self {
        foreach($this->getSignatories()->toArray() as $signatory) {
            $this->removeSignatory($signatory);
        }

        $this->signatories = new ArrayCollection();
        foreach($signatories as $signatory) {
            $this->addSignatory($signatory);
        }
        return $this;
    }

    public function getInventoryLocationMissions(): Collection {
        return $this->inventoryLocationMissions;
    }

    public function addInventoryLocationMission(InventoryLocationMission $inventoryLocationMission): self {
        if (!$this->inventoryLocationMissions->contains($inventoryLocationMission)) {
            $this->inventoryLocationMissions[] = $inventoryLocationMission;
            $inventoryLocationMission->setLocation($this);
        }

        return $this;
    }

    public function removeInventoryLocationMission(InventoryLocationMission $inventoryLocationMission): self {
        if ($this->inventoryLocationMissions->removeElement($inventoryLocationMission)) {
            if ($inventoryLocationMission->getLocation() === $this) {
                $inventoryLocationMission->setLocation(null);
            }
        }

        return $this;
    }

    public function setInventoryLocationMissions(?iterable $inventoryLocationMissions): self {
        foreach($this->getInventoryLocationMissions()->toArray() as $inventoryLocationMission) {
            $this->removeInventoryLocationMission($inventoryLocationMission);
        }

        $this->inventoryLocationMissions = new ArrayCollection();
        foreach($inventoryLocationMissions ?? [] as $inventoryLocationMission) {
            $this->addInventoryLocationMission($inventoryLocationMission);
        }

        return $this;
    }

    public function getZone(): ?Zone {
        return $this->zone;
    }

    public function setZone(?Zone $zone): self {
        if($this->zone && $this->zone !== $zone) {
            $this->zone->removeLocation($this);
        }
        $this->zone = $zone;
        $zone?->addLocation($this);

        return $this;
    }

    public function getInventoryMissionRules(): Collection {
        return $this->inventoryMissionRules;
    }

    public function addInventoryMissionRule(InventoryMissionRule $inventoryMissionRule): self {
        if (!$this->inventoryMissionRules->contains($inventoryMissionRule)) {
            $this->inventoryMissionRules[] = $inventoryMissionRule;
            $inventoryMissionRule->addLocation($this);
        }

        return $this;
    }

    public function removeInventoryMissionRule(InventoryMissionRule $inventoryMissionRule): self {
        if ($this->inventoryMissionRules->removeElement($inventoryMissionRule)) {
            $inventoryMissionRule->removeLocation($this);
        }

        return $this;
    }

    public function setLocations(?iterable $inventoryMissionRules): self {
        foreach($this->getInventoryMissionRules()->toArray() as $inventoryMissionRule) {
            $this->removeInventoryMissionRule($inventoryMissionRule);
        }

        $this->inventoryMissionRules = new ArrayCollection();
        foreach($inventoryMissionRules ?? [] as $inventoryMissionRule) {
            $this->addInventoryMissionRule($inventoryMissionRule);
        }

        return $this;
    }

}
