<?php

namespace App\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity(repositoryClass="App\Repository\ArrivageRepository")
 */
class Arrivage
{
    const STATUS_CONFORME = 'conforme';
    const STATUS_RESERVE = 'reserve';
	const STATUS_LITIGE = 'litige';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseur", inversedBy="arrivages")
     */
    private $fournisseur;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Chauffeur", inversedBy="arrivages")
     */
    private $chauffeur;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $noTracking;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $numeroBL;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="arrivagesDestinataire")
     */
    private $destinataire;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Utilisateur", inversedBy="arrivagesAcheteur")
     */
    private $acheteurs;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $numeroReception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transporteur", inversedBy="arrivages")
     */
    private $transporteur;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private $numeroArrivage;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="arrivagesUtilisateur")
     */
    private $utilisateur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Colis", mappedBy="arrivage")
     */
    private $colis;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PieceJointe", mappedBy="arrivage")
     */
    private $attachements;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isUrgent;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="arrivages")
     */
    private $statut;

	/**
	 * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampLibre", inversedBy="arrivages")
	 */
	private $valeurChampLibre;


	public function __construct()
                {
                    $this->acheteurs = new ArrayCollection();
                    $this->colis = new ArrayCollection();
                    $this->attachements = new ArrayCollection();
                    $this->valeurChampLibre = new ArrayCollection();
                }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): string {
        /** @var Colis[] $colisCollection */
        $colisCollection = $this->colis->toArray();
        $isLitige = false;
        foreach($colisCollection as $colis) {
            /** @var Litige[] $litiges */
            $litiges = $colis->getLitiges()->toArray();
            foreach ($litiges as $litige) {
                $status = $litige->getStatus();
                $isLitige = !isset($status) || !$status->isTreated();
                if ($isLitige) {
                    break;
                }
            }

            if ($isLitige) {
                break;
            }
        }
        return $isLitige ? self::STATUS_LITIGE : self::STATUS_CONFORME;
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

    public function getChauffeur(): ?Chauffeur
    {
        return $this->chauffeur;
    }

    public function setChauffeur(?Chauffeur $chauffeur): self
    {
        $this->chauffeur = $chauffeur;

        return $this;
    }

    public function getNoTracking(): ?string
    {
        return $this->noTracking;
    }

    public function setNoTracking(?string $noTracking): self
    {
        $this->noTracking = $noTracking;

        return $this;
    }

    public function getNumeroBL(): ?string
    {
        return $this->numeroBL;
    }

    public function setNumeroBL(?string $numeroBL): self
    {
        $this->numeroBL = $numeroBL;

        return $this;
    }

    public function getDestinataire(): ?Utilisateur
    {
        return $this->destinataire;
    }

    public function setDestinataire(?Utilisateur $destinataire): self
    {
        $this->destinataire = $destinataire;

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getAcheteurs(): Collection
    {
        return $this->acheteurs;
    }

    public function addAcheteur(Utilisateur $acheteur): self
    {
        if (!$this->acheteurs->contains($acheteur)) {
            $this->acheteurs[] = $acheteur;
        }

        return $this;
    }

    public function removeAcheteur(Utilisateur $acheteur): self
    {
        if ($this->acheteurs->contains($acheteur)) {
            $this->acheteurs->removeElement($acheteur);
        }

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


    public function getTransporteur(): ?Transporteur
    {
        return $this->transporteur;
    }

    public function setTransporteur(?Transporteur $transporteur): self
    {
        $this->transporteur = $transporteur;

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

    public function getNumeroArrivage(): ?string
    {
        return $this->numeroArrivage;
    }

    public function setNumeroArrivage(string $numeroArrivage): self {
        $this->numeroArrivage = $numeroArrivage;
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
    /**
     * @return Collection|Colis[]
     */
    public function getColis(): Collection
    {
        return $this->colis;
    }

    public function addColis(Colis $colis): self
    {
        if (!$this->colis->contains($colis)) {
            $this->colis[] = $colis;
            $colis->setArrivage($this);
        }

        return $this;
    }

    public function removeColis(Colis $colis): self
    {
        if ($this->colis->contains($colis)) {
            $this->colis->removeElement($colis);
            // set the owning side to null (unless already changed)
            if ($colis->getArrivage() === $this) {
                $colis->setArrivage(null);
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
     * @return Collection|PieceJointe[]
     */
    public function getAttachements(): Collection
    {
        return $this->attachements;
    }

    public function addAttachement(PieceJointe $attachement): self
    {
        if (!$this->attachements->contains($attachement)) {
            $this->attachements[] = $attachement;
            $attachement->setArrivage($this);
        }

        return $this;
    }

    public function removeAttachement(PieceJointe $attachement): self
    {
        if ($this->attachements->contains($attachement)) {
            $this->attachements->removeElement($attachement);
            // set the owning side to null (unless already changed)
            if ($attachement->getArrivage() === $this) {
                $attachement->setArrivage(null);
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

}
