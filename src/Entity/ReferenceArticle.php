<?php

namespace App\Entity;

use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReferenceArticleRepository")
 */
class ReferenceArticle
{

    const CATEGORIE = 'referenceArticle';
    const STATUT_ACTIF = 'actif';
    const STATUT_INACTIF = 'inactif';

    const TYPE_QUANTITE_REFERENCE = 'reference';
    const TYPE_QUANTITE_ARTICLE = 'article';

    const BARCODE_PREFIX = 'REF';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $libelle;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $reference;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $barCode;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteDisponible;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteReservee;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteStock;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LigneArticle", mappedBy="reference")
     */
    private $ligneArticles;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampLibre", mappedBy="articleReference")
     */
    private $valeurChampsLibres;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="referenceArticles")
     */
    private $type;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ArticleFournisseur", mappedBy="referenceArticle")
     */
    private $articlesFournisseur;

    /**
     * @ORM\Column(type="string", length=16, nullable=true)
     */
    private $typeQuantite;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="referenceArticles")
     */
    private $statut;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CollecteReference", mappedBy="referenceArticle")
     */
    private $collecteReferences;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\OrdreCollecteReference", mappedBy="referenceArticle")
     */
    private $ordreCollecteReferences;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceptionReferenceArticle", mappedBy="referenceArticle")
     */
    private $receptionReferenceArticles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="referenceArticles")
     */
    private $emplacement;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="refArticle")
     */
    private $mouvements;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $expiryDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\InventoryCategory", inversedBy="refArticle")
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\InventoryEntry", mappedBy="refArticle")
     */
    private $inventoryEntries;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\InventoryCategoryHistory", mappedBy="refArticle")
     */
    private $inventoryCategoryHistory;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\InventoryMission", mappedBy="refArticles")
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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $limitSecurity;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $limitWarning;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isUrgent;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateEmergencyTriggered;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LigneArticlePreparation", mappedBy="reference")
     */
    private $ligneArticlePreparations;


    public function __construct()
    {
        $this->ligneArticles = new ArrayCollection();
        $this->valeurChampsLibres = new ArrayCollection();
        $this->articlesFournisseur = new ArrayCollection();
        $this->collecteReferences = new ArrayCollection();
        $this->receptionReferenceArticles = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->inventoryEntries = new ArrayCollection();
        $this->inventoryCategoryHistory = new ArrayCollection();
        $this->inventoryMissions = new ArrayCollection();
        $this->ordreCollecteReferences = new ArrayCollection();
        $this->ligneArticlePreparations = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
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

    public function __toString()
    {
        return $this->reference;
    }

    public function getQuantiteDisponible(): ?int
    {
        return $this->quantiteDisponible;
    }

    public function setQuantiteDisponible(?int $quantiteDisponible): self
    {
        $this->quantiteDisponible = $quantiteDisponible;

        return $this;
    }

    public function getQuantiteReservee(): ?int
    {
        return $this->quantiteReservee;
    }

    public function setQuantiteReservee(?int $quantiteReservee): self
    {
        $this->quantiteReservee = $quantiteReservee;

        return $this;
    }

    public function getQuantiteStock(): ?int
    {
        return $this->quantiteStock;
    }

    public function setQuantiteStock(?int $quantiteStock): self
    {
        $this->quantiteStock = $quantiteStock;

        return $this;
    }

    /**
     * @return Collection|LigneArticle[]
     */
    public function getLigneArticles(): Collection
    {
        return $this->ligneArticles;
    }

    public function addLigneArticle(LigneArticle $ligneArticle): self
    {
        if (!$this->ligneArticles->contains($ligneArticle)) {
            $this->ligneArticles[] = $ligneArticle;
            $ligneArticle->setReference($this);
        }

        return $this;
    }

    public function removeLigneArticle(LigneArticle $ligneArticle): self
    {
        if ($this->ligneArticles->contains($ligneArticle)) {
            $this->ligneArticles->removeElement($ligneArticle);
            // set the owning side to null (unless already changed)
            if ($ligneArticle->getReference() === $this) {
                $ligneArticle->setReference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ValeurChampLibre[]
     */
    public function getValeurChampsLibres(): Collection
    {
        return $this->valeurChampsLibres;
    }

    public function addValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampLibre)) {
            $this->valeurChampsLibres[] = $valeurChampLibre;
            $valeurChampLibre->addArticleReference($this);
        }

        return $this;
    }

    public function removeValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampLibre);
            $valeurChampLibre->removeArticleReference($this);
        }

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

    /**
     * @return Collection|ArticleFournisseur[]
     */
    public function getArticlesFournisseur(): Collection
    {
        return $this->articlesFournisseur;
    }

    public function addArticleFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if (!$this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur[] = $articlesFournisseur;
            $articlesFournisseur->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeArticleFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if ($this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur->removeElement($articlesFournisseur);
            // set the owning side to null (unless already changed)
            if ($articlesFournisseur->getReferenceArticle() === $this) {
                $articlesFournisseur->setReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getTypeQuantite(): ?string
    {
        return $this->typeQuantite;
    }

    public function setTypeQuantite(?string $typeQuantite): self
    {
        $this->typeQuantite = $typeQuantite;

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

    /**
     * @return Collection|CollecteReference[]
     */
    public function getCollecteReferences(): Collection
    {
        return $this->collecteReferences;
    }

    public function addCollecteReference(CollecteReference $collecteReference): self
    {
        if (!$this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences[] = $collecteReference;
            $collecteReference->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeCollecteReference(CollecteReference $collecteReference): self
    {
        if ($this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences->removeElement($collecteReference);
            // set the owning side to null (unless already changed)
            if ($collecteReference->getReferenceArticle() === $this) {
                $collecteReference->setReferenceArticle(null);
            }
        }

        return $this;
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
     * @return Collection|ReceptionReferenceArticle[]
     */
    public function getReceptionReferenceArticles(): Collection
    {
        return $this->receptionReferenceArticles;
    }

    public function addReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if (!$this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles[] = $receptionReferenceArticle;
            $receptionReferenceArticle->setReferenceArticle($this);
        }
        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if ($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if ($receptionReferenceArticle->getReferenceArticle() === $this) {
                $receptionReferenceArticle->setReferenceArticle(null);
            }
            return $this;
        }
    }

    public function addArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if (!$this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur[] = $articlesFournisseur;
            $articlesFournisseur->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if ($this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur->removeElement($articlesFournisseur);
            // set the owning side to null (unless already changed)
            if ($articlesFournisseur->getReferenceArticle() === $this) {
                $articlesFournisseur->setReferenceArticle(null);
            }
        }

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
            $mouvement->setRefArticle($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getRefArticle() === $this) {
                $mouvement->setRefArticle(null);
            }
        }

        return $this;
    }

    public function addValeurChampsLibre(ValeurChampLibre $valeurChampsLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres[] = $valeurChampsLibre;
            $valeurChampsLibre->addArticleReference($this);
        }

        return $this;
    }

    public function removeValeurChampsLibre(ValeurChampLibre $valeurChampsLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampsLibre);
            $valeurChampsLibre->removeArticleReference($this);
        }

        return $this;
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
            $inventoryEntry->setRefArticle($this);
        }

        return $this;
    }

    public function removeInventoryEntry(InventoryEntry $inventoryEntry): self
    {
        if ($this->inventoryEntries->contains($inventoryEntry)) {
            $this->inventoryEntries->removeElement($inventoryEntry);
            // set the owning side to null (unless already changed)
            if ($inventoryEntry->getRefArticle() === $this) {
                $inventoryEntry->setRefArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|InventoryCategoryHistory[]
     */
    public function getInventoryCategoryHistory(): Collection
    {
        return $this->inventoryCategoryHistory;
    }

    public function addInventoryCategoryHistory(InventoryCategoryHistory $inventoryCategoryHistory): self
    {
        if (!$this->inventoryCategoryHistory->contains($inventoryCategoryHistory)) {
            $this->inventoryCategoryHistory[] = $inventoryCategoryHistory;
            $inventoryCategoryHistory->setRefArticle($this);
        }

        return $this;
    }

    public function removeInventoryCategoryHistory(InventoryCategoryHistory $inventoryCategoryHistory): self
    {
        if ($this->inventoryCategoryHistory->contains($inventoryCategoryHistory)) {
            $this->inventoryCategoryHistory->removeElement($inventoryCategoryHistory);
            // set the owning side to null (unless already changed)
            if ($inventoryCategoryHistory->getRefArticle() === $this) {
                $inventoryCategoryHistory->setRefArticle(null);
            }
        }

        return $this;
    }


    public function getCategory(): ?InventoryCategory
    {
        return $this->category;
    }

    public function setCategory(?InventoryCategory $category): self
    {
        $this->category = $category;

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
            $inventoryMission->addRefArticle($this);
        }

        return $this;
    }

    public function removeInventoryMission(InventoryMission $inventoryMission): self
    {
        if ($this->inventoryMissions->contains($inventoryMission)) {
            $this->inventoryMissions->removeElement($inventoryMission);
            $inventoryMission->removeRefArticle($this);
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

    public function getLimitSecurity()
    {
        return $this->limitSecurity;
    }

    public function setLimitSecurity($limitSecurity): self
    {
        $this->limitSecurity = $limitSecurity;
        return $this;
    }

    public function getLimitWarning()
    {
        return $this->limitWarning;
    }

    public function setLimitWarning($limitWarning): self
    {
        $this->limitWarning = $limitWarning;
        return $this;
    }

    /**
     * @return Collection|OrdreCollecteReference[]
     */
    public function getOrdreCollecteReferences(): Collection
    {
        return $this->ordreCollecteReferences;
    }

    public function addOrdreCollecteReference(OrdreCollecteReference $ordreCollecteReference): self
    {
        if (!$this->ordreCollecteReferences->contains($ordreCollecteReference)) {
            $this->ordreCollecteReferences[] = $ordreCollecteReference;
            $ordreCollecteReference->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeOrdreCollecteReference(OrdreCollecteReference $ordreCollecteReference): self
    {
        if ($this->ordreCollecteReferences->contains($ordreCollecteReference)) {
            $this->ordreCollecteReferences->removeElement($ordreCollecteReference);
            // set the owning side to null (unless already changed)
            if ($ordreCollecteReference->getReferenceArticle() === $this) {
                $ordreCollecteReference->setReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getIsUrgent(): ?bool
    {
        return $this->isUrgent;
    }

    public function setIsUrgent(?bool $isUrgent): self
    {
        $this->isUrgent = $isUrgent;

        return $this;
    }

    public function getCalculatedStockQuantity(): int
    {
        $totalQuantity = 0;
        if ($this->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            foreach ($this->getArticlesFournisseur() as $articleFournisseur) {
                foreach ($articleFournisseur->getArticles() as $article) {
                    if ($article->getStatut()->getNom() === Article::STATUT_ACTIF) {
                        $totalQuantity += $article->getQuantite();
                    }
                }
            }
        }
        return ($this->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE)
            ? $this->getQuantiteStock()
            : $totalQuantity;
    }

    public function getDateEmergencyTriggered(): ?\DateTimeInterface
    {
        return $this->dateEmergencyTriggered;
    }

    public function setDateEmergencyTriggered(?\DateTimeInterface $dateEmergencyTriggered): self
    {
        $this->dateEmergencyTriggered = $dateEmergencyTriggered;

        return $this;
    }

    /**
     * @return Collection|LigneArticlePreparation[]
     */
    public function getLigneArticlePreparations(): Collection
    {
        return $this->ligneArticlePreparations;
    }

    public function addLigneArticlePreparation(LigneArticlePreparation $ligneArticlePreparation): self
    {
        if (!$this->ligneArticlePreparations->contains($ligneArticlePreparation)) {
            $this->ligneArticlePreparations[] = $ligneArticlePreparation;
            $ligneArticlePreparation->setReference($this);
        }

        return $this;
    }

    public function removeLigneArticlePreparation(LigneArticlePreparation $ligneArticlePreparation): self
    {
        if ($this->ligneArticlePreparations->contains($ligneArticlePreparation)) {
            $this->ligneArticlePreparations->removeElement($ligneArticlePreparation);
            // set the owning side to null (unless already changed)
            if ($ligneArticlePreparation->getReference() === $this) {
                $ligneArticlePreparation->setReference(null);
            }
        }

        return $this;
    }

}
