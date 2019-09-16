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
     * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampsLibre", mappedBy="article")
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
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoryInv", inversedBy="article")
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MvtInventory", mappedBy="article")
     */
    private $mvtInventories;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\HistoryCategory", mappedBy="article")
     */
    private $historyCategories;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MissionInv", mappedBy="ManyToOne")
     */
    private $missionInvs;


    public function __construct()
    {
        $this->preparations = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->valeurChampsLibres = new ArrayCollection();
        $this->mvtInventories = new ArrayCollection();
        $this->historyCategories = new ArrayCollection();
        $this->missionInvs = new ArrayCollection();
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
     * @return Collection|ValeurChampsLibre[]
     */
    public function getValeurChampsLibres(): Collection
    {
        return $this->valeurChampsLibres;
    }

    public function addValeurChampsLibre(ValeurChampsLibre $valeurChampsLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres[] = $valeurChampsLibre;
            $valeurChampsLibre->addArticle($this);
        }

        return $this;
    }

    public function removeValeurChampsLibre(ValeurChampsLibre $valeurChampsLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampsLibre);
            $valeurChampsLibre->removeArticle($this);
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

    /**
     * @return Collection|MvtInventory[]
     */
    public function getMvtInventories(): Collection
    {
        return $this->mvtInventories;
    }

    public function addMvtInventory(MvtInventory $mvtInventory): self
    {
        if (!$this->mvtInventories->contains($mvtInventory)) {
            $this->mvtInventories[] = $mvtInventory;
            $mvtInventory->setArticle($this);
        }

        return $this;
    }

    public function removeMvtInventory(MvtInventory $mvtInventory): self
    {
        if ($this->mvtInventories->contains($mvtInventory)) {
            $this->mvtInventories->removeElement($mvtInventory);
            // set the owning side to null (unless already changed)
            if ($mvtInventory->getArticle() === $this) {
                $mvtInventory->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|HistoryCategory[]
     */
    public function getHistoryCategories(): Collection
    {
        return $this->historyCategories;
    }

    public function addHistoryCategory(HistoryCategory $historyCategory): self
    {
        if (!$this->historyCategories->contains($historyCategory)) {
            $this->historyCategories[] = $historyCategory;
            $historyCategory->setArticle($this);
        }

        return $this;
    }

    public function removeHistoryCategory(HistoryCategory $historyCategory): self
    {
        if ($this->historyCategories->contains($historyCategory)) {
            $this->historyCategories->removeElement($historyCategory);
            // set the owning side to null (unless already changed)
            if ($historyCategory->getArticle() === $this) {
                $historyCategory->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|MissionInv[]
     */
    public function getMissionInvs(): Collection
    {
        return $this->missionInvs;
    }

    public function addMissionInv(MissionInv $missionInv): self
    {
        if (!$this->missionInvs->contains($missionInv)) {
            $this->missionInvs[] = $missionInv;
            $missionInv->setManyToOne($this);
        }

        return $this;
    }

    public function removeMissionInv(MissionInv $missionInv): self
    {
        if ($this->missionInvs->contains($missionInv)) {
            $this->missionInvs->removeElement($missionInv);
            // set the owning side to null (unless already changed)
            if ($missionInv->getManyToOne() === $this) {
                $missionInv->setManyToOne(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?CategoryInv
    {
        return $this->category;
    }

    public function setCategory(?CategoryInv $category): self
    {
        $this->category = $category;

        return $this;
    }
}
