<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticleRepository")
 * @UniqueEntity("reference")
 */
class Article
{
    const CATEGORIE = 'article';
    const STATUT_ACTIF = 'actif';
    const STATUT_INACTIF = 'inactif';
    const STATUT_EN_TRANSIT = 'en transit';
    const CONFORM = 1;
    const NOT_CONFORM = 0;

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
     * @ORM\ManyToMany(targetEntity="App\Entity\Preparation", mappedBy="articles")
     */
    private $preparations;

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
     * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="articles")
     */
    private $reception;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\InventoryEntry", mappedBy="article")
     */
    private $inventoryEntries;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\InventoryMission", inversedBy="articles")
     */
    private $inventoryMission;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $hasInventoryAnomaly = false;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateLastInventory;


    public function __construct()
    {
        $this->preparations = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->valeurChampsLibres = new ArrayCollection();
        $this->inventoryEntries = new ArrayCollection();
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
            $preparation->addArticle($this);
        }

        return $this;
    }

    public function removePreparation(Preparation $preparation): self
    {
        if ($this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            $preparation->removeArticle($this);
        }

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
        return  $this->emplacement;
    }

    public function setEmplacement(?Emplacement  $emplacement): self
    {
        $this->emplacement =  $emplacement;
        return  $this;
    }
    public function getDemande(): ?Demande
    {
        return  $this->demande;
    }

    public function setDemande(?Demande  $demande): self
    {
        $this->demande =  $demande;
        return  $this;
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

    public function getReception(): ?Reception
    {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self
    {
        $this->reception = $reception;

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
	public function getInventoryEntries(): Collection
    {
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
    }

    public function getInventoryMission(): ?InventoryMission
    {
        return $this->inventoryMission;
    }

    public function setInventoryMission(?InventoryMission $inventoryMission): self
    {
        $this->inventoryMission = $inventoryMission;

        return $this;
    }

    public function getHasInventoryAnomaly(): ?bool
    {
        return $this->hasInventoryAnomaly;
    }

    public function setHasInventoryAnomaly(bool $hasInventoryAnomaly): self
    {
        $this->hasInventoryAnomaly = $hasInventoryAnomaly;

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

}
