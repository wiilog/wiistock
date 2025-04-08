<?php

namespace App\Entity;

use App\Entity\Emergency\Emergency;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Interfaces\AttachmentContainer;
use App\Entity\Tracking\Pack;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Traits\CleanedCommentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\ArrivageRepository;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArrivageRepository::class)]
class Arrivage implements AttachmentContainer {

    use CleanedCommentTrait;
    use FreeFieldsManagerTrait;
    use AttachmentTrait;

    public const AVAILABLE_ARRIVAL_NUMBER_FORMATS = [
        UniqueNumberService::DATE_COUNTER_FORMAT_ARRIVAL_LONG => "aammjjhhmmss-xx",
        UniqueNumberService::DATE_COUNTER_FORMAT_ARRIVAL_SHORT => "aammjjhhmmxx",
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'arrivages')]
    private ?Fournisseur $fournisseur = null;

    #[ORM\ManyToOne(targetEntity: Chauffeur::class, inversedBy: 'arrivages')]
    private ?Chauffeur $chauffeur = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $noTracking = null;

    #[ORM\Column(type: 'json')]
    private ?array $numeroCommandeList = [];

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'receivedArrivals')]
    #[ORM\JoinTable("arrival_receiver")]
    #[ORM\JoinColumn(name: "arrival_id", referencedColumnName: "id")]
    #[ORM\InverseJoinColumn(name: "user_id", referencedColumnName: "id")]
    private Collection $receivers;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'arrivagesAcheteur')]
    private Collection $acheteurs;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $numeroReception = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class, inversedBy: 'arrivages')]
    private ?Transporteur $transporteur = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $date = null;

    #[ORM\Column(type: 'string', length: 32, unique: true, nullable: true)]
    private ?string $numeroArrivage = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'arrivagesUtilisateur')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\OneToMany(mappedBy: 'arrivage', targetEntity: Pack::class, cascade: ["persist"])]
    private Collection $packs;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\OneToMany(mappedBy: 'arrivage', targetEntity: 'Attachment')]
    private Collection $attachements;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isUrgent = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'arrivages')]
    private ?Statut $statut = null;

    #[ORM\OneToMany(mappedBy: 'lastArrival', targetEntity: Urgence::class)]
    private Collection $urgences; //TODO WIIS-12642

    #[ORM\OneToMany(mappedBy: 'lastArrival', targetEntity: Emergency::class)]
    private Collection $emergencies;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $customs = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $frozen = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $projectNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $businessUnit = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'arrivals')]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $dropLocation = null;

    #[ORM\OneToOne(mappedBy: 'arrival', targetEntity: Reception::class)]
    private ?Reception $reception = null;

    #[ORM\ManyToMany(targetEntity: TruckArrivalLine::class, mappedBy: 'arrivals')]
    private Collection $truckArrivalLines;

    #[ORM\ManyToOne(targetEntity: TruckArrival::class)]
    private ?TruckArrival $truckArrival = null;

    #[ORM\ManyToOne(inversedBy: 'arrivals')]
    private ?TrackingEmergency $trackingEmergency = null;

    public function __construct() {
        $this->acheteurs = new ArrayCollection();
        $this->packs = new ArrayCollection();
        $this->attachements = new ArrayCollection();
        $this->urgences = new ArrayCollection(); // TODO WIIS-12642
        $this->emergencies = new ArrayCollection();
        $this->numeroCommandeList = [];
        $this->truckArrivalLines = new ArrayCollection();
        $this->receivers = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getFournisseur(): ?Fournisseur {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): self {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getChauffeur(): ?Chauffeur {
        return $this->chauffeur;
    }

    public function setChauffeur(?Chauffeur $chauffeur): self {
        $this->chauffeur = $chauffeur;

        return $this;
    }

    public function getNoTracking(): ?string {
        return $this->noTracking;
    }

    public function setNoTracking(?string $noTracking): self {
        $this->noTracking = $noTracking;

        return $this;
    }

    public function getNumeroCommandeList(): array {
        return $this->numeroCommandeList;
    }

    public function setNumeroCommandeList(array $numeroCommandeList): self {
        $this->numeroCommandeList = array_reduce(
            $numeroCommandeList,
            function(array $result, string $numeroCommande) {
                $trimmed = trim($numeroCommande);
                if(!empty($trimmed)) {
                    $result[] = $trimmed;
                }
                return $result;
            },
            []
        );

        return $this;
    }

    public function addNumeroCommande(string $numeroCommande): self {
        $trimmed = trim($numeroCommande);
        if(!empty($trimmed)) {
            $this->numeroCommandeList[] = $trimmed;
        }

        return $this;
    }

    public function removeNumeroCommande(string $numeroCommande): self {
        $index = array_search($numeroCommande, $this->numeroCommandeList);

        if($index !== false) {
            array_splice($this->numeroCommandeList, $index, 1);
        }

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getAcheteurs(): Collection {
        $buyers = array_merge(
            $this->getInitialAcheteurs()->toArray(),
            $this->getUrgencesAcheteurs()->toArray()
        );
        return new ArrayCollection(array_unique($buyers));
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getUrgencesAcheteurs(): Collection { // TODO WIIS-12642
        $emergencyBuyer = $this->urgences
            ->map(function(Urgence $urgence) {
                return $urgence->getBuyer();
            })
            ->filter(fn($buyer) => $buyer !== null);

        return new ArrayCollection(array_unique($emergencyBuyer->toArray()));
    }

    public function getEmergencyBuyer(): Collection {
        $emergencyBuyer = $this->emergencies //
        ->map(function(Emergency $emergency) {
            return $emergency->getBuyer();
        })
            ->filter(fn($buyer) => $buyer !== null);

        return new ArrayCollection(array_unique($emergencyBuyer->toArray()));
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getInitialAcheteurs(): Collection {
        return $this->acheteurs;
    }

    public function addAcheteur(Utilisateur $acheteur): self {
        if(!$this->acheteurs->contains($acheteur)) {
            $this->acheteurs[] = $acheteur;
        }

        return $this;
    }

    public function removeAcheteur(Utilisateur $acheteur): self {
        if($this->acheteurs->contains($acheteur)) {
            $this->acheteurs->removeElement($acheteur);
        }

        return $this;
    }

    public function removeAllAcheteur(): self {
        foreach($this->acheteurs as $acheteur) {
            $this->acheteurs->removeElement($acheteur);
        }
        return $this;
    }

    public function getNumeroReception(): ?string {
        return $this->numeroReception;
    }

    public function setNumeroReception(?string $numeroReception): self {
        $this->numeroReception = $numeroReception;

        return $this;
    }

    public function getTransporteur(): ?Transporteur {
        return $this->transporteur;
    }

    public function setTransporteur(?Transporteur $transporteur): self {
        $this->transporteur = $transporteur;

        return $this;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(?DateTime $date): self {
        $this->date = $date;

        return $this;
    }

    public function getNumeroArrivage(): ?string {
        return $this->numeroArrivage;
    }

    public function setNumeroArrivage(string $numeroArrivage): self {
        $this->numeroArrivage = $numeroArrivage;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    /**
     * @return Collection|Pack[]
     */
    public function getPacks(): Collection {
        return $this->packs;
    }

    public function addPack(Pack $pack): self {
        if(!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
            $pack->setArrivage($this);
        }

        return $this;
    }

    public function removePack(Pack $pack): self {
        if($this->packs->contains($pack)) {
            $this->packs->removeElement($pack);
            // set the owning side to null (unless already changed)
            if($pack->getArrivage() === $this) {
                $pack->setArrivage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Emergency[]
     */
    public function getEmergencies(): Collection {
        return $this->emergencies;
    }

    /**
     * @return void
     */
    public function clearEmergencies(): void {
        foreach($this->emergencies as $emergency) {
            if($emergency->getLastArrival() === $this) {
                $emergency->setLastArrival(null);
            }
        }
        $this->urgences->clear();
    }

    public function addEmergency(Emergency $emergency): self {
        if(!$this->emergencies->contains($emergency)) {
            $this->emergencies[] = $emergency;
            $emergency->setLastArrival($this);
        }

        return $this;
    }

    public function removeEmergency(Emergency $emergency): self {
        if($this->emergencies->contains($emergency)) {
            $this->emergencies->removeElement($emergency);
            // set the owning side to null (unless already changed)
            if($emergency->getLastArrival() === $this) {
                $emergency->setLastArrival(null);
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
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection {
        return $this->attachements;
    }

    public function addAttachment(Attachment $attachment): self {
        if(!$this->attachements->contains($attachment)) {
            $this->attachements[] = $attachment;
            $attachment->setArrivage($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self {
        if($this->attachements->contains($attachment)) {
            $this->attachements->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if($attachment->getArrivage() === $this) {
                $attachment->setArrivage(null);
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

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

        return $this;
    }

    public function getCustoms(): ?bool {
        return $this->customs;
    }

    public function setCustoms(?bool $customs): self {
        $this->customs = $customs;

        return $this;
    }

    public function getFrozen(): ?bool {
        return $this->frozen;
    }

    public function setFrozen(?bool $frozen): self {
        $this->frozen = $frozen;

        return $this;
    }

    public function getProjectNumber(): ?string {
        return $this->projectNumber;
    }

    public function setProjectNumber(?string $projectNumber): self {
        $this->projectNumber = $projectNumber;

        return $this;
    }

    public function getBusinessUnit(): ?string {
        return $this->businessUnit;
    }

    public function setBusinessUnit(?string $businessUnit): self {
        $this->businessUnit = $businessUnit;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getDropLocation(): ?Emplacement {
        return $this->dropLocation;
    }

    public function setDropLocation(?Emplacement $dropLocation): self {
        $this->dropLocation = $dropLocation;

        return $this;
    }

    /**
     * @return Collection<int, TruckArrivalLine>
     */
    public function getTruckArrivalLines(): Collection
    {
        return $this->truckArrivalLines;
    }

    public function addTruckArrivalLine(TruckArrivalLine $truckArrivalLine): self
    {
        if (!$this->truckArrivalLines->contains($truckArrivalLine)) {
            $this->truckArrivalLines[] = $truckArrivalLine;
            $truckArrivalLine->addArrival($this);
        }

        return $this;
    }

    public function removeTruckArrivalLine(TruckArrivalLine $truckArrivalLine): self
    {
        if ($this->truckArrivalLines->removeElement($truckArrivalLine)) {
            $truckArrivalLine->removeArrival($this);
        }

        return $this;
    }

    public function setTruckarrivalLines(?iterable $truckArrivalLines): self {
        foreach($this->getTruckArrivalLines()->toArray() as $truckArrivalLine) {
            $this->removeTruckArrivalLine($truckArrivalLine);
        }

        $this->truckArrivalLines = new ArrayCollection();
        foreach($truckArrivalLines ?? [] as $truckArrivalLine) {
            $this->addTruckArrivalLine($truckArrivalLine);
        }

        return $this;
    }

    public function getReception(): ?Reception {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self {
        if($this->reception && $this->reception->getArrival() !== $this) {
            $oldReception = $this->reception;
            $this->reception = null;
            $oldReception->setArrival(null);
        }
        $this->reception = $reception;
        if($this->reception && $this->reception->getArrival() !== $this) {
            $this->reception->setArrival($this);
        }

        return $this;
    }

    public function getReceivers(): Collection {
        return $this->receivers;
    }

    public function addReceiver(Utilisateur $receiver): self {
        if(!$this->receivers->contains($receiver)) {
            $this->receivers[] = $receiver;
            if(!$receiver->getReceivedArrivals()->contains($this)) {
                $receiver->addReceivedArrival($this);
            }
        }

        return $this;
    }

    public function removeReceiver(Utilisateur $receiver): self {
        if($this->receivers->removeElement($receiver)) {
            $receiver->removeReceivedArrival($this);
        }

        return $this;
    }

    public function getTruckArrival(): ?TruckArrival {
        $firstArrivalLine = $this->getTruckArrivalLines()->first();

        return $firstArrivalLine
            ? $firstArrivalLine->getTruckArrival()
            : $this->truckArrival;
    }

    public function setTruckArrival(?TruckArrival $truckArrival): self {
        $this->truckArrival = $truckArrival;

        return $this;
    }

    public function getTrackingEmergency(): ?TrackingEmergency {
        return $this->trackingEmergency;
    }

    public function setTrackingEmergency(?TrackingEmergency $trackingEmergency): self {
        $this->trackingEmergency = $trackingEmergency;

        return $this;
    }
}
