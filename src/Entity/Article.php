<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticlesRepository")
 * @UniqueEntity("nom")
 */
class Article
{
    const CATEGORIE = 'article';
    const STATUT_RECEPTION_EN_COURS = 'en cours de reception';
    const STATUT_DEMANDE_STOCK = 'demande de mise en stock';
    const STATUT_EN_STOCK = 'en stock';
    const STATUT_DESTOCK = 'destokage';
    const STATUT_ANOMALIE = 'anomalie';
    const STATUT_DEMANDE_SORTIE = 'demande de sortie';
    const STATUT_COLLECTE = 'collecté';
    const STATUT_LIVRAISON = 'en livraison';
    const STATUT_RECUPERE = 'récupéré';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="articles")
     */
    private $refArticle;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="articles")
     */
    private $reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="articles")
     */
    private $direction;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="position")
     */
    private $position;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Demande", mappedBy="articles")
     */
    private $demandes;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Collecte", mappedBy="articles")
     */
    private $collectes;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="articles")
     */
    private $Statut;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteARecevoir;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteCollectee;

    /**
     * @ORM\Column(type="boolean")
     */
    private $etat;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $reference;

    
    public function __construct()
    {
        $this->preparations = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->collectes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

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
        return $this->nom;
    }

    public function getRefArticle(): ?ReferenceArticle
    {
        return $this->refArticle;
    }

    public function setRefArticle(?ReferenceArticle $refArticle): self
    {
        $this->refArticle = $refArticle;

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

    public function getDirection(): ?Emplacement
    {
        return $this->direction;
    }

    public function setDirection(?Emplacement $direction): self
    {
        $this->direction = $direction;

        return $this;
    }

    public function getPosition(): ?Emplacement
    {
        return $this->position;
    }

    public function setPosition(?Emplacement $position): self
    {
        $this->position = $position;

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
     * @return Collection|Demande[]
     */
    public function getDemandes(): Collection
    {
        return $this->demandes;
    }

    public function addDemande(Demande $demande): self
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes[] = $demande;
            $demande->addArticle($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self
    {
        if ($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            $demande->removeArticle($this);
        }

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
        return $this->Statut;
    }

    public function setStatut(?Statut $Statut): self
    {
        $this->Statut = $Statut;

        return $this;
    }

    public function getQuantiteARecevoir(): ?int
    {
        return $this->quantiteARecevoir;
    }

    public function setQuantiteARecevoir(?int $quantiteARecevoir): self
    {
        $this->quantiteARecevoir = $quantiteARecevoir;

        return $this;
    }

    public function getQuantiteCollectee(): ?int
    {
        return $this->quantiteCollectee;
    }

    public function setQuantiteCollectee(?int $quantiteCollectee): self
    {
        $this->quantiteCollectee = $quantiteCollectee;

        return $this;
    }

    public function getEtat(): ?bool
    {
        return $this->etat;
    }

    public function setEtat(bool $etat): self
    {
        $this->etat = $etat;

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
}
