<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticleRepository")
 * @UniqueEntity("reference")
 */
class Article
{
    const CATEGORIE = 'article';

    const STATUT_ACTIF = 'disponible';
    const STATUT_INACTIF = 'consommé';
    const STATUT_EN_TRANSIT = 'en transit';
    const STATUT_EN_LITIGE = 'en litige';

    const USED_ASSOC_COLLECTE = 0;
    const USED_ASSOC_DEMANDE = 1;
    const USED_ASSOC_LITIGE = 2;
    const USED_ASSOC_INVENTORY = 3;
    const USED_ASSOC_MOUVEMENT = 4;
    const USED_ASSOC_STATUT_NOT_AVAILABLE = 5;
    const USED_ASSOC_NONE = -1;

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
     * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampLibre", mappedBy="article")
     */
    private $valeurChampsLibres;

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
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="articles")
     */
    private $preparation;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementTraca", mappedBy="article")
     */
    private $mouvementTracas;


    public function __construct()
    {
        $this->collectes = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->valeurChampsLibres = new ArrayCollection();
        $this->inventoryEntries = new ArrayCollection();
        $this->inventoryMissions = new ArrayCollection();
        $this->litiges = new ArrayCollection();
        $this->ordreCollecte = new ArrayCollection();
        $this->mouvementTracas = new ArrayCollection();

        $this->quantite = 0;
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

    public function __toString()
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
            $valeurChampLibre->addArticle($this);
        }

        return $this;
    }

    public function removeValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampLibre);
            $valeurChampLibre->removeArticle($this);
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

    public function addValeurChampsLibre(ValeurChampLibre $valeurChampsLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres[] = $valeurChampsLibre;
            $valeurChampsLibre->addArticle($this);
        }

        return $this;
    }

    public function removeValeurChampsLibre(ValeurChampLibre $valeurChampsLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampsLibre);
            $valeurChampsLibre->removeArticle($this);
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
        $this->preparation = $preparation;

        return $this;
    }

    /**
     * @return Collection|MouvementTraca[]
     */
    public function getMouvementTracas(): Collection
    {
        return $this->mouvementTracas;
    }

    public function addMouvementTraca(MouvementTraca $mouvementTraca): self
    {
        if (!$this->mouvementTracas->contains($mouvementTraca)) {
            $this->mouvementTracas[] = $mouvementTraca;
            $mouvementTraca->setArticle($this);
        }

        return $this;
    }

    public function removeMouvementTraca(MouvementTraca $mouvementTraca): self
    {
        if ($this->mouvementTracas->contains($mouvementTraca)) {
            $this->mouvementTracas->removeElement($mouvementTraca);
            // set the owning side to null (unless already changed)
            if ($mouvementTraca->getArticle() === $this) {
                $mouvementTraca->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getUsedAssociation(): int
    {
        return (
            count($this->getCollectes()) > 0 ? self::USED_ASSOC_COLLECTE :
            ($this->getDemande() !== null ? self::USED_ASSOC_DEMANDE :
            (count($this->getLitiges()) > 0 ? self::USED_ASSOC_LITIGE :
            (count($this->getInventoryEntries()) > 0 ? self::USED_ASSOC_INVENTORY :
            (count($this->getMouvementTracas()) > 0 ? self::USED_ASSOC_MOUVEMENT :
            ($this->getStatut()->getNom() !== self::STATUT_ACTIF ? self::USED_ASSOC_STATUT_NOT_AVAILABLE :
            self::USED_ASSOC_NONE)))))
        );
    }

    public function isInRequestsInProgress(): bool {
        $request = $this->getDemande();
        $preparation = $this->getPreparation();
        return (
            (
                $request
                && $request->getStatut()
                && $request->getStatut()->getNom() !== Demande::STATUT_BROUILLON
            )
            ||
            $preparation
        );
    }
}
