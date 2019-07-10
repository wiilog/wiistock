<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CollecteRepository")
 */
class Collecte
{
    const CATEGORIE = 'collecte';

    const STATUS_COLLECTE = 'collecté';
    const STATUS_A_TRAITER = 'à traiter';
    const STATUS_BROUILLON = 'brouillon';

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

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->collecteReferences = new ArrayCollection();
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
}
