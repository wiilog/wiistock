<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CollecteRepository")
 */
class Collecte extends FreeFieldEntity
{
    const CATEGORIE = 'collecte';

    const STATUT_COLLECTE = 'collecté';
    const STATUT_INCOMPLETE = 'partiellement collecté';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_BROUILLON = 'brouillon';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $numero;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
    private $validationDate;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $objet;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="collectes")
     */
    private $pointCollecte;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="collectes")
     */
    private $demandeur;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Article", inversedBy="collectes")
     */
    private $articles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="collectes")
     */

    private $statut;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CollecteReference", mappedBy="collecte")
     */
    private $collecteReferences;

    /**
     * @ORM\Column(type="boolean")
     */
    private $stockOrDestruct;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="collectes")
     */
    private $type;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="collecteOrder")
	 */
	private $mouvements;

	/**
	 * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampLibre", inversedBy="demandesCollecte")
	 */
	private $valeurChampLibre;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\OrdreCollecte", mappedBy="demandeCollecte")
	 */
	private $ordreCollecte;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->collecteReferences = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->valeurChampLibre = new ArrayCollection();
        $this->ordreCollecte = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getDemandeur(): ?Utilisateur
    {
        return $this->demandeur;
    }

    public function setDemandeur(?Utilisateur $demandeur): self
    {
        $this->demandeur = $demandeur;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
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

    public function getObjet(): ?string
    {
        return $this->objet;
    }

    public function setObjet(?string $objet): self
    {
        $this->objet = $objet;

        return $this;
    }

    public function getPointCollecte(): ?Emplacement
    {
        return $this->pointCollecte;
    }

    public function setPointCollecte(?Emplacement $pointCollecte): self
    {
        $this->pointCollecte = $pointCollecte;

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
            $collecteReference->setCollecte($this);
        }

        return $this;
    }

    public function removeCollecteReference(CollecteReference $collecteReference): self
    {
        if ($this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences->removeElement($collecteReference);
            // set the owning side to null (unless already changed)
            if ($collecteReference->getCollecte() === $this) {
                $collecteReference->setCollecte(null);
            }
        }

        return $this;
    }

    public function getStockOrDestruct(): ?bool
    {
        return $this->stockOrDestruct;
    }

    public function setStockOrDestruct(bool $stockOrDestruct): self
    {
        $this->stockOrDestruct = $stockOrDestruct;

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
    public function getValeurChampLibre(): Collection
    {
        return $this->valeurChampLibre;
    }

    public function addValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if (!$this->valeurChampLibre->contains($valeurChampLibre)) {
            $this->valeurChampLibre[] = $valeurChampLibre;
        }

        return $this;
    }

    public function removeValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if ($this->valeurChampLibre->contains($valeurChampLibre)) {
            $this->valeurChampLibre->removeElement($valeurChampLibre);
        }

        return $this;
    }

    /**
     * @return Collection|OrdreCollecte[]
     */
    public function getOrdresCollecte(): Collection
    {
        return $this->ordreCollecte;
    }

    public function getValidationDate(): ?\DateTimeInterface
    {
        return $this->validationDate;
    }

    public function setValidationDate(?\DateTimeInterface $validationDate): self
    {
        $this->validationDate = $validationDate;

        return $this;
    }

    public function addOrdreCollecte(OrdreCollecte $ordreCollecte): self
    {
        if (!$this->ordreCollecte->contains($ordreCollecte)) {
            $this->ordreCollecte[] = $ordreCollecte;
            $ordreCollecte->setDemandeCollecte($this);
        }

        return $this;
    }

    public function removeOrdreCollecte(OrdreCollecte $ordreCollecte): self
    {
        if ($this->ordreCollecte->contains($ordreCollecte)) {
            $this->ordreCollecte->removeElement($ordreCollecte);
            // set the owning side to null (unless already changed)
            if ($ordreCollecte->getDemandeCollecte() === $this) {
                $ordreCollecte->setDemandeCollecte(null);
            }
        }

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
            $mouvement->setCollecteOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getCollecteOrder() === $this) {
                $mouvement->setCollecteOrder(null);
            }
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
}
