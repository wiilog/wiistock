<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceptionRepository")
 */
class Reception
{
    const STATUT_EN_ATTENTE = 'en attente de réception';
    const STATUT_RECEPTION_PARTIELLE = 'réception partielle';
    const STATUT_RECEPTION_TOTALE = 'réception totale';
    const STATUT_ANOMALIE = 'anomalie';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseur", inversedBy="receptions")
	 * @ORM\JoinColumn(nullable=true)
     */
    private $fournisseur;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $numeroReception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="receptions")
	 * @ORM\JoinColumn(nullable=true)
     */
    private $utilisateur;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="receptions")
     */
    private $statut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateAttendue;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCommande;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $reference;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceptionReferenceArticle", mappedBy="reception")
     */
    private $receptionReferenceArticles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="receptions")
     */
    private $type;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampLibre", inversedBy="receptions")
     */
    private $valeurChampLibre;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transporteur", inversedBy="reception")
     */
    private $transporteur;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateFinReception;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="reception")
     */
    private $demandes;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="receptionOrder")
	 */
	private $mouvements;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement")
	 */
	private $location;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $emergencyTriggered;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementTraca", mappedBy="reception")
     */
    private $mouvementsTraca;

    public function __construct()
    {
        $this->receptionReferenceArticles = new ArrayCollection();
        $this->valeurChampLibre = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->mouvementsTraca = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): self
    {
        $this->fournisseur = $fournisseur;

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


    public function __toString()
    {
        return $this->commentaire;
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

    public function getNumeroReception(): ?string
    {
        return $this->numeroReception;
    }

    public function setNumeroReception(?string $numeroReception): self
    {
        $this->numeroReception = $numeroReception;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

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

    public function getDateAttendue(): ?\DateTimeInterface
    {
        return $this->dateAttendue;
    }

    public function setDateAttendue(?\DateTimeInterface $dateAttendue): self
    {
        $this->dateAttendue = $dateAttendue;

        return $this;
    }

    public function getDateCommande(): ?\DateTimeInterface
    {
        return $this->dateCommande;
    }

    public function setDateCommande(?\DateTimeInterface $dateCommande): self
    {
        $this->dateCommande = $dateCommande;

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
            $receptionReferenceArticle->setReception($this);
        }

        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if ($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if ($receptionReferenceArticle->getReception() === $this) {
                $receptionReferenceArticle->setReception(null);
            }
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

    public function getDateFinReception(): ?\DateTimeInterface
    {
        return $this->dateFinReception;
    }

    public function setDateFinReception(?\DateTimeInterface $dateFinReception): self
    {
        $this->dateFinReception = $dateFinReception;

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
            $demande->setReception($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self
    {
        if ($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if ($demande->getReception() === $this) {
                $demande->setReception(null);
            }
        }

        return $this;
    }

    public function getTransporteur(): ?Transporteur
    {
        return $this->transporteur;
    }

    public function setTransporteur(?Transporteur $transporteur): self
    {
        $this->transporteur = $transporteur;

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
            $mouvement->setReceptionOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getReceptionOrder() === $this) {
                $mouvement->setReceptionOrder(null);
            }
        }

        return $this;
    }

    public function getLocation(): ?Emplacement
    {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getEmergencyTriggered(): ?bool
    {
        return $this->emergencyTriggered;
    }

    public function setEmergencyTriggered(?bool $emergencyTriggered): self
    {
        $this->emergencyTriggered = $emergencyTriggered;

        return $this;
    }

    /**
     * @return Collection|MouvementTraca[]
     */
    public function getMouvementsTraca(): Collection
    {
        return $this->mouvementsTraca;
    }

    public function addMouvementsTraca(MouvementTraca $mouvementsTraca): self
    {
        if (!$this->mouvementsTraca->contains($mouvementsTraca)) {
            $this->mouvementsTraca[] = $mouvementsTraca;
            $mouvementsTraca->setReception($this);
        }

        return $this;
    }

    public function removeMouvementsTraca(MouvementTraca $mouvementsTraca): self
    {
        if ($this->mouvementsTraca->contains($mouvementsTraca)) {
            $this->mouvementsTraca->removeElement($mouvementsTraca);
            // set the owning side to null (unless already changed)
            if ($mouvementsTraca->getReception() === $this) {
                $mouvementsTraca->setReception(null);
            }
        }

        return $this;
    }
}
