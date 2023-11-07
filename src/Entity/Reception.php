<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Traits\CleanedCommentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\ReceptionRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Google\Service\AdMob\Date;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: ReceptionRepository::class)]
class Reception {

    use FreeFieldsManagerTrait;
    use AttachmentTrait;
    use CleanedCommentTrait;

    const NUMBER_PREFIX = 'R';
    const STATUT_EN_ATTENTE = 'en attente de réception';
    const STATUT_RECEPTION_PARTIELLE = 'réception partielle';
    const STATUT_RECEPTION_TOTALE = 'réception totale';
    const STATUT_ANOMALIE = 'anomalie';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'receptions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $date = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "receptions")]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'receptions')]
    private ?Statut $statut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTime $dateAttendue = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $dateCommande = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $orderNumber;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'receptions')]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class, inversedBy: 'reception')]
    private ?Transporteur $transporteur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $dateFinReception = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $lastAssociation = null;

    #[ORM\OneToMany(mappedBy: 'reception', targetEntity: 'App\Entity\DeliveryRequest\Demande')]
    private Collection $demandes;

    #[ORM\OneToMany(mappedBy: 'reception', targetEntity: TransferRequest::class)]
    private Collection $transferRequests;

    #[ORM\OneToMany(mappedBy: 'receptionOrder', targetEntity: MouvementStock::class)]
    private Collection $mouvements;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $storageLocation = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $urgentArticles = null;

    #[ORM\OneToMany(mappedBy: 'reception', targetEntity: TrackingMovement::class)]
    private Collection $trackingMovements;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $manualUrgent = null;

    #[ORM\OneToMany(mappedBy: 'reception', targetEntity: PurchaseRequestLine::class)]
    private Collection $purchaseRequestLines;

    #[ORM\ManyToMany(targetEntity: Arrivage::class, inversedBy: 'receptions')]
    private Collection $arrivals;

    #[ORM\OneToMany(mappedBy: 'reception', targetEntity: ReceptionLine::class)]
    private Collection $lines;

    public function __construct() {
        $this->demandes = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->purchaseRequestLines = new ArrayCollection();
        $this->lines = new ArrayCollection();
        $this->arrivals = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @return Collection<int, ReceptionLine>
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(ReceptionLine $line): self {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setReception($this);
        }

        return $this;
    }

    public function removeLine(ReceptionLine $line): self {
        if ($this->lines->removeElement($line)) {
            if ($line->getReception() === $this) {
                $line->setReception(null);
            }
        }

        return $this;
    }

    public function getFournisseur(): ?Fournisseur {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): self {
        $this->fournisseur = $fournisseur;

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

    public function __toString() {
        return $this->commentaire ?? '';
    }

    public function getDate(): ?DateTimeInterface {
        return $this->date;
    }

    public function setDate(?DateTimeInterface $date): self {
        $this->date = $date;

        return $this;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(?string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

        return $this;
    }

    public function getDateAttendue(): ?DateTime {
        return $this->dateAttendue;
    }

    public function setDateAttendue(?DateTime $dateAttendue): self {
        $this->dateAttendue = $dateAttendue;

        return $this;
    }

    public function getDateCommande(): ?DateTimeInterface {
        return $this->dateCommande;
    }

    public function setDateCommande(?DateTimeInterface $dateCommande): self {
        $this->dateCommande = $dateCommande;

        return $this;
    }

    public function getOrderNumber(): ?array {
        return $this->orderNumber;
    }

    public function setOrderNumber(?array $orderNumber): self {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getDateFinReception(): ?DateTimeInterface {
        return $this->dateFinReception;
    }

    public function setDateFinReception(?DateTimeInterface $dateFinReception): self {
        $this->dateFinReception = $dateFinReception;

        return $this;
    }

    /**
     * @return Collection|Demande[]
     */
    public function getTransferRequest(): Collection {
        return $this->transferRequests;
    }

    public function addTransferRequest(TransferRequest $request): self {
        if(!$this->transferRequests->contains($request)) {
            $this->transferRequests[] = $request;
            $request->setReception($this);
        }

        return $this;
    }

    public function removeTransferRequest(TransferRequest $request): self {
        if($this->transferRequests->contains($request)) {
            $this->transferRequests->removeElement($request);
            // set the owning side to null (unless already changed)
            if($request->getReception() === $this) {
                $request->setReception(null);
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
            $demande->setReception($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self {
        if($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if($demande->getReception() === $this) {
                $demande->setReception(null);
            }
        }

        return $this;
    }

    public function getTransporteur(): ?Transporteur {
        return $this->transporteur;
    }

    public function setTransporteur(?Transporteur $transporteur): self {
        $this->transporteur = $transporteur;

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
            $mouvement->setReceptionOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getReceptionOrder() === $this) {
                $mouvement->setReceptionOrder(null);
            }
        }

        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self {
        $this->location = $location;

        return $this;
    }

    public function getStorageLocation(): ?Emplacement {
        return $this->storageLocation;
    }

    public function setStorageLocation(?Emplacement $storageLocation): self {
        $this->storageLocation = $storageLocation;

        return $this;
    }

    public function isManualUrgent(): ?bool {
        return $this->manualUrgent;
    }

    public function setManualUrgent(?bool $manualUrgent): self {
        $this->manualUrgent = $manualUrgent;
        return $this;
    }

    public function hasUrgentArticles(): ?bool {
        return $this->urgentArticles;
    }

    public function setUrgentArticles(?bool $urgentArticles): self {
        $this->urgentArticles = $urgentArticles;

        return $this;
    }

    /**
     * @return Collection|TrackingMovement[]
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if(!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setReception($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if($trackingMovement->getReception() === $this) {
                $trackingMovement->setReception(null);
            }
        }

        return $this;
    }

    public function getPurchaseRequestLines(): Collection {
        return $this->purchaseRequestLines;
    }

    public function addPurchaseRequestLine(PurchaseRequestLine $purchaseRequestLines): self {
        if(!$this->purchaseRequestLines->contains($purchaseRequestLines)) {
            $this->purchaseRequestLines[] = $purchaseRequestLines;
            $purchaseRequestLines->setReception($this);
        }

        return $this;
    }

    public function removePurchaseRequestLine(PurchaseRequestLine $purchaseRequestLines): self {
        if($this->purchaseRequestLines->removeElement($purchaseRequestLines)) {
            if($purchaseRequestLines->getReception() === $this) {
                $purchaseRequestLines->setReception(null);
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

    /**
     * @return ReceptionReferenceArticle[]
     */
    public function getReceptionReferenceArticles(): array {
        return Stream::from($this->getLines()->toArray())
            ->flatMap(fn(ReceptionLine $line) => $line->getReceptionReferenceArticles()->toArray())
            ->toArray();
    }

    public function getLine(?Pack $pack): ?ReceptionLine {
        return Stream::from($this->getLines()->toArray())
            ->find(fn(ReceptionLine $line) => $line->getPack()?->getId() === $pack?->getId());
    }

    public function hasPacks(): bool {
        return Stream::from($this->getLines())
            ->some(fn(ReceptionLine $line) => $line->hasPack());
    }

    public function isPartial(): bool {
        return Stream::from($this->getReceptionReferenceArticles())
            ->some(fn(ReceptionReferenceArticle $receptionReferenceArticle) => $receptionReferenceArticle->getQuantite());
    }

    public function getArrivals(): Collection {
        return $this->arrivals;
    }

    public function addArrival(Arrivage $arrival): self {
        if (!$this->arrivals->contains($arrival)) {
            $this->arrivals[] = $arrival;
            $arrival->addReception($this);
        }

        return $this;
    }

    public function removeArrival(Arrivage $arrival): self {
        if ($this->arrivals->removeElement($arrival)) {
            $arrival->removeReception($this);
        }

        return $this;
    }

    public function setArrivals(?iterable $arrivals): self {
        foreach($this->getArrivals()->toArray() as $arrival) {
            $this->removeArrival($arrival);
        }

        $this->arrivals = new ArrayCollection();
        foreach($arrivals ?? [] as $arrival) {
            $this->addArrival($arrival);
        }

        return $this;
    }
}
