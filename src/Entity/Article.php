<?php

namespace App\Entity;

use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticleRepository")
 * @UniqueEntity("reference")
 */
class Article extends FreeFieldEntity
{
    const CATEGORIE = 'article';

    const STATUT_ACTIF = 'disponible';
    const STATUT_INACTIF = 'consommé';
    const STATUT_EN_TRANSIT = 'en transit';
    const STATUT_EN_LITIGE = 'en litige';

    const USED_ASSOC_COLLECTE = 0;
    const USED_ASSOC_LITIGE = 1;
    const USED_ASSOC_INVENTORY = 2;
    const USED_ASSOC_STATUT_NOT_AVAILABLE = 3;
    const USED_ASSOC_PREPA_IN_PROGRESS = 4;

    const BARCODE_PREFIX = 'ART';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $reference;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $barCode;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Collecte", mappedBy="articles")
     */
    private $collectes;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="articles")
     */
    private $statut;

    /**
     * @ORM\Column(type="boolean")
     */
    private $conform;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="article")
     */
    private $mouvements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ArticleFournisseur", inversedBy="articles")
     */
    private $articleFournisseur;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="articles")
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="articles")
     */
    private $emplacement;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Demande", inversedBy="articles")
     */
    private $demande;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteAPrelever;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantitePrelevee;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReceptionReferenceArticle", inversedBy="articles")
     * @ORM\JoinColumn(nullable=true)
     */
    private $receptionReferenceArticle;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\InventoryEntry", mappedBy="article")
     */
    private $inventoryEntries;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\InventoryMission", inversedBy="articles")
     */
    private $inventoryMissions;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $prixUnitaire;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateLastInventory;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\OrdreCollecte", inversedBy="articles")
     */
    private $ordreCollecte;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Litige", mappedBy="articles")
     */
    private $litiges;

    /**
     * @var Preparation|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="articles")
     */
    private $preparation;

    /**
     * @ORM\OneToOne(targetEntity=Pack::class, mappedBy="article", cascade={"remove"})
     */
    private $trackingPack;

    /**
     * @ORM\ManyToMany(targetEntity=TransferRequest::class, mappedBy="articles")
     */
    private $transferRequests;

    /**
     * @ORM\OneToMany(targetEntity=Alert::class, mappedBy="article")
     */
    private $alerts;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $batch;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $expiryDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $stockEntryDate;

    public function __construct()
    {
        $this->collectes = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->inventoryEntries = new ArrayCollection();
        $this->inventoryMissions = new ArrayCollection();
        $this->litiges = new ArrayCollection();
        $this->ordreCollecte = new ArrayCollection();
        $this->transferRequests = new ArrayCollection();

        $this->quantite = 0;
        $this->alerts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function __toString(): ?string
    {
        return $this->label;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

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
            $collecte->addArticle($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self
    {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            $collecte->removeArticle($this);
        }

        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getConform(): ?bool
    {
        return $this->conform;
    }

    public function setConform(bool $conform): self
    {
        $this->conform = $conform;

        return $this;
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

    public function getArticleFournisseur(): ?ArticleFournisseur
    {
        return $this->articleFournisseur;
    }

    public function setArticleFournisseur(?ArticleFournisseur $articleFournisseur): self
    {
        $this->articleFournisseur = $articleFournisseur;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEmplacement(): ?Emplacement
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): self
    {
        $this->emplacement = $emplacement;
        return $this;
    }
    public function getDemande(): ?Demande
    {
        return $this->demande;
    }

    public function setDemande(?Demande $demande): self
    {
        $this->demande = $demande;
        return $this;
    }

    public function getQuantiteAPrelever(): ?int
    {
        return $this->quantiteAPrelever;
    }

    public function setQuantiteAPrelever(?int $quantiteAPrelever): self
    {
        $this->quantiteAPrelever = $quantiteAPrelever;

        return $this;
    }

    public function getPrixUnitaire()
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire($prixUnitaire): self
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    /**
     * @return Selectable|Collection|MouvementStock[]
     */
    public function getMouvements() {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self
    {
        if (!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setArticle($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getArticle() === $this) {
                $mouvement->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|InventoryEntry[]
     */
    public function getInventoryEntries(): Collection {
        return $this->inventoryEntries;
    }

    public function addInventoryEntry(InventoryEntry $inventoryEntry): self
    {
        if (!$this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries[] = $inventoryEntry;
            $inventoryEntry->setArticle($this);
        }

        return $this;
    }

    public function removeInventoryEntry(InventoryEntry $inventoryEntry): self
    {
        if ($this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries->removeElement($inventoryEntry);
            // set the owning side to null (unless already changed)
            if ($inventoryEntry->getArticle() === $this) {
                $inventoryEntry->setArticle(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|InventoryMission[]
     */
    public function getInventoryMissions(): Collection
    {
        return $this->inventoryMissions;
    }

    public function addInventoryMission(InventoryMission $inventoryMission): self
    {
        if (!$this->inventoryMissions->contains($inventoryMission)) {
            $this->inventoryMissions[] = $inventoryMission;
        }

        return $this;
    }

    public function removeInventoryMission(InventoryMission $inventoryMission): self
    {
        if ($this->inventoryMissions->contains($inventoryMission)) {
            $this->inventoryMissions->removeElement($inventoryMission);
        }

        return $this;
    }

    public function getDateLastInventory(): ?\DateTimeInterface
    {
        return $this->dateLastInventory;
    }

    public function setDateLastInventory(?\DateTimeInterface $dateLastInventory): self
    {
        $this->dateLastInventory = $dateLastInventory;

        return $this;
    }

    public function getBarCode(): ?string
    {
        return $this->barCode;
    }

    public function setBarCode(?string $barCode): self
    {
        $this->barCode = $barCode;

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
            $litige->addArticle($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            $litige->removeArticle($this);
        }

        return $this;
    }

    /**
     * @return Collection|OrdreCollecte[]
     */
    public function getOrdreCollecte(): Collection
    {
        return $this->ordreCollecte;
    }

    public function addOrdreCollecte(OrdreCollecte $ordreCollecte): self
    {
        if (!$this->ordreCollecte->contains($ordreCollecte)) {
            $this->ordreCollecte[] = $ordreCollecte;
        }

        return $this;
    }

    public function removeOrdreCollecte(OrdreCollecte $ordreCollecte): self
    {
        if ($this->ordreCollecte->contains($ordreCollecte)) {
            $this->ordreCollecte->removeElement($ordreCollecte);
        }

        return $this;
    }

    public function getReceptionReferenceArticle(): ?ReceptionReferenceArticle
    {
        return $this->receptionReferenceArticle;
    }

    public function setReceptionReferenceArticle(?ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        $this->receptionReferenceArticle = $receptionReferenceArticle;

        return $this;
    }

    public function getQuantitePrelevee(): ?int
    {
        return $this->quantitePrelevee;
    }

    public function setQuantitePrelevee(?int $quantitePrelevee): self
    {
        $this->quantitePrelevee = $quantitePrelevee;

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        if ($this->preparation && $this->preparation !== $preparation) {
            $this->preparation->removeArticle($this);
        }

        $this->preparation = $preparation;

        if ($this->preparation) {
            $this->preparation->addArticle($this);
        }

        return $this;
    }

    /**
     * @return null|Pack
     */
    public function getTrackingPack(): ?Pack {
        return $this->trackingPack;
    }

    /**
     * @param Pack|null
     * @return Article
     */
    public function setTrackingPack(?Pack $pack): self {
        if (isset($this->trackingPack)
            && $this->trackingPack !== $pack) {
            $this->trackingPack->setArticle(null);
        }
        $this->trackingPack = $pack;
        if (isset($this->trackingPack)
            && $this->trackingPack->getArticle() !== $this) {
            $this->trackingPack->setArticle($this);
        }
        return $this;
    }

    public function getTrackingMovements(): Collection {
        return isset($this->trackingPack)
            ? $this->trackingPack->getTrackingMovements()
            : new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getUsedAssociation(): ?int
    {
        $preparationCannotBeDeleted = $this->getPreparation()
                ? $this->getPreparation()->getStatut()->getNom() !== Preparation::STATUT_A_TRAITER
                : false;
        return (
            count($this->getCollectes()) > 0
                ? self::USED_ASSOC_COLLECTE
                : (count($this->getLitiges()) > 0
                    ? self::USED_ASSOC_LITIGE
                    : (count($this->getInventoryEntries()) > 0
                        ? self::USED_ASSOC_INVENTORY
                        : ($this->getStatut()->getNom() === self::STATUT_INACTIF
                            ? self::USED_ASSOC_STATUT_NOT_AVAILABLE
                            : ($preparationCannotBeDeleted
                                ? self::USED_ASSOC_PREPA_IN_PROGRESS
                                : null
                            )
                        )
                    )
                )
        );
    }

    public function isInRequestsInProgress(): bool {
        $request = $this->getDemande();
        $preparation = $this->getPreparation();
        $articleFournisseur = $this->getArticleFournisseur();
        $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
        return (
            (
                $request
                && $request->getStatut()
                && $request->getStatut()->getNom() !== Demande::STATUT_BROUILLON
            )
            || $preparation
            || ($referenceArticle && $referenceArticle->isInRequestsInProgress())
        );
    }

    public function isUsedInQuantityChangingProcesses(): bool {
        $demande = $this->getDemande();
        $inProgress = $demande ? $demande->needsToBeProcessed() : false;
        if (!$inProgress) {
            $collectes = $this->getOrdreCollecte();
            /** @var OrdreCollecte $collecte */
            foreach ($collectes as $collecte) {
                if ($collecte->needsToBeProcessed()) {
                    $inProgress = true;
                    break;
                }
            }
        }
        return $inProgress;
    }

    /**
     * @return Collection|TransferRequest[]
     */
    public function getTransferRequests(): Collection
    {
        return $this->transferRequests;
    }

    public function addTransferRequest(TransferRequest $transferRequest): self
    {
        if (!$this->transferRequests->contains($transferRequest)) {
            $this->transferRequests[] = $transferRequest;
            $transferRequest->addArticle($this);
        }

        return $this;
    }

    public function removeTransferRequest(TransferRequest $transferRequest): self
    {
        if ($this->transferRequests->contains($transferRequest)) {
            $this->transferRequests->removeElement($transferRequest);
            $transferRequest->removeArticle($this);
        }

        return $this;
    }

    /**
     * @return Collection|Alert[]
     */
    public function getAlerts(): Collection
    {
        return $this->alerts;
    }

    public function addAlert(Alert $alert): self
    {
        if (!$this->alerts->contains($alert)) {
            $this->alerts[] = $alert;
            $alert->setArticle($this);
        }

        return $this;
    }

    public function removeAlert(Alert $alert): self
    {
        if ($this->alerts->contains($alert)) {
            $this->alerts->removeElement($alert);
            // set the owning side to null (unless already changed)
            if ($alert->getArticle() === $this) {
                $alert->setArticle(null);
            }
        }

        return $this;
    }

    public function isExpired(): ?bool
    {
        if($this->getExpiryDate()) {
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));

            return $now >= $this->getExpiryDate();
        } else {
            return null;
        }
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getBatch(): ?string
    {
        return $this->batch;
    }

    public function setBatch(?string $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function getStockEntryDate(): ?\DateTimeInterface
    {
        return $this->stockEntryDate;
    }

    public function setStockEntryDate(?\DateTimeInterface $stockEntryDate): self
    {
        $this->stockEntryDate = $stockEntryDate;

        return $this;
    }
}
