<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticlesRepository")
 */
class Articles
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $etat;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacements")
     */
    private $emplacement;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zones")
     */
    private $zone;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais")
     */
    private $quai;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferencesArticles")
     */
    private $reference;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Entrees", inversedBy="articles")
     */
    private $entrees;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Sorties", inversedBy="articles")
     */
    private $sorties;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Transferts", inversedBy="articles")
     */
    private $transferts;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $date_comptabilisation;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $n_document;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $libelle;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $code_magasin;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $consigne_entree;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $emplacement_reel;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $consigne_sortie;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $n;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $designation;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $code_tache;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $statut;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $valeur;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $photo;

    public function __construct()
    {
        $this->entrees = new ArrayCollection();
        $this->sorties = new ArrayCollection();
        $this->transferts = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    public function getEmplacement(): ?Emplacements
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacements $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    public function getZone(): ?Zones
    {
        return $this->zone;
    }

    public function setZone(?Zones $zone): self
    {
        $this->zone = $zone;

        return $this;
    }

    public function getQuai(): ?Quais
    {
        return $this->quai;
    }

    public function setQuai(?Quais $quai): self
    {
        $this->quai = $quai;

        return $this;
    }

    public function getReference(): ?ReferencesArticles
    {
        return $this->reference;
    }

    public function setReference(?ReferencesArticles $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return Collection|Entrees[]
     */
    public function getEntrees(): Collection
    {
        return $this->entrees;
    }

    public function addEntree(Entrees $entree): self
    {
        if (!$this->entrees->contains($entree)) {
            $this->entrees[] = $entree;
        }

        return $this;
    }

    public function removeEntree(Entrees $entree): self
    {
        if ($this->entrees->contains($entree)) {
            $this->entrees->removeElement($entree);
        }

        return $this;
    }

    /**
     * @return Collection|Sorties[]
     */
    public function getSorties(): Collection
    {
        return $this->sorties;
    }

    public function addSorty(Sorties $sorty): self
    {
        if (!$this->sorties->contains($sorty)) {
            $this->sorties[] = $sorty;
        }

        return $this;
    }

    public function removeSorty(Sorties $sorty): self
    {
        if ($this->sorties->contains($sorty)) {
            $this->sorties->removeElement($sorty);
        }

        return $this;
    }

    /**
     * @return Collection|Transferts[]
     */
    public function getTransferts(): Collection
    {
        return $this->transferts;
    }

    public function addTransfert(Transferts $transfert): self
    {
        if (!$this->transferts->contains($transfert)) {
            $this->transferts[] = $transfert;
        }

        return $this;
    }

    public function removeTransfert(Transferts $transfert): self
    {
        if ($this->transferts->contains($transfert)) {
            $this->transferts->removeElement($transfert);
        }

        return $this;
    }

    public function getDateComptabilisation(): ?\DateTimeInterface
    {
        return $this->date_comptabilisation;
    }

    public function setDateComptabilisation(?\DateTimeInterface $date_comptabilisation): self
    {
        $this->date_comptabilisation = $date_comptabilisation;

        return $this;
    }

    public function getNDocument(): ?string
    {
        return $this->n_document;
    }

    public function setNDocument(?string $n_document): self
    {
        $this->n_document = $n_document;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getCodeMagasin(): ?string
    {
        return $this->code_magasin;
    }

    public function setCodeMagasin(?string $code_magasin): self
    {
        $this->code_magasin = $code_magasin;

        return $this;
    }

    public function getConsigneEntree(): ?string
    {
        return $this->consigne_entree;
    }

    public function setConsigneEntree(?string $consigne_entree): self
    {
        $this->consigne_entree = $consigne_entree;

        return $this;
    }

    public function getEmplacementReel(): ?string
    {
        return $this->emplacement_reel;
    }

    public function setEmplacementReel(?string $emplacement_reel): self
    {
        $this->emplacement_reel = $emplacement_reel;

        return $this;
    }

    public function getConsigneSortie(): ?string
    {
        return $this->consigne_sortie;
    }

    public function setConsigneSortie(?string $consigne_sortie): self
    {
        $this->consigne_sortie = $consigne_sortie;

        return $this;
    }

    public function getN(): ?string
    {
        return $this->n;
    }

    public function setN(?string $n): self
    {
        $this->n = $n;

        return $this;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(?string $designation): self
    {
        $this->designation = $designation;

        return $this;
    }

    public function getCodeTache(): ?string
    {
        return $this->code_tache;
    }

    public function setCodeTache(?string $code_tache): self
    {
        $this->code_tache = $code_tache;

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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;

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

    public function getValeur(): ?float
    {
        return $this->valeur;
    }

    public function setValeur(?float $valeur): self
    {
        $this->valeur = $valeur;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;

        return $this;
    }
}
