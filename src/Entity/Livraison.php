<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Tracking\TrackingMovement;
use App\Repository\LivraisonRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LivraisonRepository::class)]
class Livraison {

    const CATEGORIE = 'livraison';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_LIVRE = 'livré';
    const STATUT_INCOMPLETE = 'partiellement livré';

    const DELIVERY_NOTE_DATA = [
        'consignor' => true,
        'deliveryAddress' => false,
        'deliveryNumber' => false,
        'deliveryDate' => false,
        'dispatchEmergency' => false,
        'packs' => false,
        'salesOrderNumber' => false,
        'wayBill' => false,
        'customerPONumber' => false,
        'customerPODate' => false,
        'respOrderNb' => false,
        'projectNumber' => false,
        'username' => false,
        'userPhone' => false,
        'userFax' => false,
        'buyer' => false,
        'buyerPhone' => false,
        'buyerFax' => false,
        'invoiceNumber' => false,
        'soldNumber' => false,
        'invoiceTo' => false,
        'soldTo' => false,
        'endUserNo' => false,
        'deliverNo' => false,
        'endUser' => false,
        'deliverTo' => false,
        'consignor2' => true,
        'date' => false,
        'notes' => true,
    ];

    const WAYBILL_DATA = [
        'carrier' => false,
        'dispatchDate' => false,
        'consignor' => false,
        'receiver' => false,
        'consignorUsername' => false,
        'consignorEmail' => false,
        'receiverUsername' => false,
        'receiverEmail' => false,
        'locationFrom' => true,
        'locationTo' => true,
        'notes' => true,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $numero = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'livraisons')]
    private ?Emplacement $destination = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'livraisons')]
    private ?Statut $statut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $date = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $dateFin = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'livraisons')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\OneToOne(inversedBy: 'livraison', targetEntity: Preparation::class)]
    private ?Preparation $preparation = null;

    #[ORM\OneToMany(mappedBy: 'livraisonOrder', targetEntity: MouvementStock::class)]
    private Collection $mouvements;

    #[ORM\OneToMany(mappedBy: 'delivery', targetEntity: TrackingMovement::class)]
    private Collection $trackingMovements;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $deliveryNoteData;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $waybillData;

    #[ORM\OneToMany(mappedBy: 'deliveryOrder', targetEntity: Attachment::class)]
    private Collection $attachements;

    public function __construct() {
        $this->mouvements = new ArrayCollection();
        $this->deliveryNoteData = [];
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNumero(): ?string {
        return $this->numero;
    }

    public function setNumero(?string $numero): self {
        $this->numero = $numero;
        return $this;
    }

    public function getDestination(): ?emplacement {
        return $this->destination;
    }

    public function setDestination(?emplacement $destination): self {
        $this->destination = $destination;
        return $this;
    }

    /**
     * @return Demande|null
     */
    public function getDemande(): ?Demande {
        return isset($this->preparation)
            ? $this->preparation->getDemande()
            : null;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self {
        $this->date = $date;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getPreparation(): ?Preparation {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self {
        $this->preparation = $preparation;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self {
        $this->dateFin = $dateFin;

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
            $mouvement->setLivraisonOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getLivraisonOrder() === $this) {
                $mouvement->setLivraisonOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isCompleted(): bool {
        return (
            isset($this->statut)
            && in_array($this->statut->getCode(), [Livraison::STATUT_LIVRE, Livraison::STATUT_INCOMPLETE])
        );
    }

    /**
     * @return array
     */
    public function getDeliveryNoteData(): array {
        return $this->deliveryNoteData ?? [];
    }

    /**
     * @param array $deliveryNoteData
     * @return self
     */
    public function setDeliveryNoteData(array $deliveryNoteData): self {
        $this->deliveryNoteData = $deliveryNoteData;
        return $this;
    }

    /**
     * @return array
     */
    public function getWaybillData(): array {
        return $this->waybillData ?? [];
    }

    /**
     * @param array $waybillData
     * @return self
     */
    public function setWaybillData(array $waybillData): self {
        $this->waybillData = $waybillData;
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
            $attachment->setDeliveryOrder($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self {
        if($this->attachements->contains($attachment)) {
            $this->attachements->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if($attachment->getDeliveryOrder() === $this) {
                $attachment->setDeliveryOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if(!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setDelivery($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if($trackingMovement->getDelivery() === $this) {
                $trackingMovement->setDelivery(null);
            }
        }

        return $this;
    }
}
