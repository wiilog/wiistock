<?php

namespace App\Entity;

use App\Entity\Traits\CommentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\ArrivageRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArrivageRepository::class)]
class Arrivage {

    use CommentTrait;
    use FreeFieldsManagerTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'arrivages')]
    private ?Fournisseur $fournisseur = null;

    #[ORM\ManyToOne(targetEntity: Chauffeur::class, inversedBy: 'arrivages')]
    private ?Chauffeur $chauffeur = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $noTracking = null;

    #[ORM\Column(type: 'json')]
    private ?array $numeroCommandeList;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'arrivagesDestinataire')]
    private ?Utilisateur $destinataire = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'arrivagesAcheteur')]
    private Collection $acheteurs;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $numeroReception = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class, inversedBy: 'arrivages')]
    private ?Transporteur $transporteur = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $date = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true, unique: true)]
    private ?string $numeroArrivage = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'arrivagesUtilisateur')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\OneToMany(targetEntity: Pack::class, mappedBy: 'arrivage')]
    private Collection $packs;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\OneToMany(targetEntity: 'Attachment', mappedBy: 'arrivage')]
    private Collection $attachements;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isUrgent = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'arrivages')]
    private ?Statut $statut = null;

    #[ORM\OneToMany(targetEntity: Urgence::class, mappedBy: 'lastArrival')]
    private Collection $urgences;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $customs = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $frozen = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $projectNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $businessUnit = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'arrivals')]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'arrivals')]
    private ?Emplacement $dropLocation = null;

    #[ORM\OneToMany(targetEntity: Reception::class, mappedBy: 'arrival')]
    private Collection $receptions;

    public function __construct() {
        $this->acheteurs = new ArrayCollection();
        $this->packs = new ArrayCollection();
        $this->attachements = new ArrayCollection();
        $this->urgences = new ArrayCollection();
        $this->numeroCommandeList = [];
        $this->receptions = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getFournisseur(): ?Fournisseur {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): self {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getChauffeur(): ?Chauffeur {
        return $this->chauffeur;
    }

    public function setChauffeur(?Chauffeur $chauffeur): self {
        $this->chauffeur = $chauffeur;

        return $this;
    }

    public function getNoTracking(): ?string {
        return $this->noTracking;
    }

    public function setNoTracking(?string $noTracking): self {
        $this->noTracking = $noTracking;

        return $this;
    }

    public function getNumeroCommandeList(): array {
        return $this->numeroCommandeList;
    }

    public function setNumeroCommandeList(array $numeroCommandeList): self {
        $this->numeroCommandeList = array_reduce(
            $numeroCommandeList,
            function(array $result, string $numeroCommande) {
                $trimmed = trim($numeroCommande);
                if(!empty($trimmed)) {
                    $result[] = $trimmed;
                }
                return $result;
            },
            []
        );

        return $this;
    }

    public function addNumeroCommande(string $numeroCommande): self {
        $trimmed = trim($numeroCommande);
        if(!empty($trimmed)) {
            $this->numeroCommandeList[] = $trimmed;
        }

        return $this;
    }

    public function removeNumeroCommande(string $numeroCommande): self {
        $index = array_search($numeroCommande, $this->numeroCommandeList);

        if($index !== false) {
            array_splice($this->numeroCommandeList, $index, 1);
        }

        return $this;
    }

    public function getDestinataire(): ?Utilisateur {
        return $this->destinataire;
    }

    public function setDestinataire(?Utilisateur $destinataire): self {
        $this->destinataire = $destinataire;

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getAcheteurs(): Collection {
        $buyers = array_merge(
            $this->getInitialAcheteurs()->toArray(),
            $this->getUrgencesAcheteurs()->toArray()
        );
        return new ArrayCollection(array_unique($buyers));
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getUrgencesAcheteurs(): Collection {
        $emergencyBuyer = $this->urgences
            ->map(function(Urgence $urgence) {
                return $urgence->getBuyer();
            });

        return new ArrayCollection(array_unique($emergencyBuyer->toArray()));
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getInitialAcheteurs(): Collection {
        return $this->acheteurs;
    }

    public function addAcheteur(Utilisateur $acheteur): self {
        if(!$this->acheteurs->contains($acheteur)) {
            $this->acheteurs[] = $acheteur;
        }

        return $this;
    }

    public function removeAcheteur(Utilisateur $acheteur): self {
        if($this->acheteurs->contains($acheteur)) {
            $this->acheteurs->removeElement($acheteur);
        }

        return $this;
    }

    public function removeAllAcheteur(): self {
        foreach($this->acheteurs as $acheteur) {
            $this->acheteurs->removeElement($acheteur);
        }
        return $this;
    }

    public function getNumeroReception(): ?string {
        return $this->numeroReception;
    }

    public function setNumeroReception(?string $numeroReception): self {
        $this->numeroReception = $numeroReception;

        return $this;
    }

    public function getTransporteur(): ?Transporteur {
        return $this->transporteur;
    }

    public function setTransporteur(?Transporteur $transporteur): self {
        $this->transporteur = $transporteur;

        return $this;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(?DateTime $date): self {
        $this->date = $date;

        return $this;
    }

    public function getNumeroArrivage(): ?string {
        return $this->numeroArrivage;
    }

    public function setNumeroArrivage(string $numeroArrivage): self {
        $this->numeroArrivage = $numeroArrivage;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    /**
     * @return Collection|Pack[]
     */
    public function getPacks(): Collection {
        return $this->packs;
    }

    public function addPack(Pack $pack): self {
        if(!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
            $pack->setArrivage($this);
        }

        return $this;
    }

    public function removePack(Pack $pack): self {
        if($this->packs->contains($pack)) {
            $this->packs->removeElement($pack);
            // set the owning side to null (unless already changed)
            if($pack->getArrivage() === $this) {
                $pack->setArrivage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Urgence[]
     */
    public function getUrgences(): Collection {
        return $this->urgences;
    }

    /**
     * @return void
     */
    public function clearUrgences(): void {
        foreach($this->urgences as $urgence) {
            if($urgence->getLastArrival() === $this) {
                $urgence->setLastArrival(null);
            }
        }
        $this->urgences->clear();
    }

    public function addUrgence(Urgence $urgence): self {
        if(!$this->urgences->contains($urgence)) {
            $this->urgences[] = $urgence;
            $urgence->setLastArrival($this);
        }

        return $this;
    }

    public function removeUrgence(Urgence $urgence): self {
        if($this->urgences->contains($urgence)) {
            $this->urgences->removeElement($urgence);
            // set the owning side to null (unless already changed)
            if($urgence->getLastArrival() === $this) {
                $urgence->setLastArrival(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?string {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self {
        $this->commentaire = $commentaire;
        $this->setCleanedComment($commentaire);

        return $this;
    }

    /**
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection {
        return $this->attachements;
    }

    public function addAttachment(Attachment $attachment): self {
        if(!$this->attachements->contains($attachment)) {
            $this->attachements[] = $attachment;
            $attachment->setArrivage($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachement): self {
        if($this->attachements->contains($attachement)) {
            $this->attachements->removeElement($attachement);
            // set the owning side to null (unless already changed)
            if($attachement->getArrivage() === $this) {
                $attachement->setArrivage(null);
            }
        }

        return $this;
    }

    public function getIsUrgent(): ?bool {
        return $this->isUrgent;
    }

    public function setIsUrgent(?bool $isUrgent): self {
        $this->isUrgent = $isUrgent;

        return $this;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

        return $this;
    }

    public function getCustoms(): ?bool {
        return $this->customs;
    }

    public function setCustoms(?bool $customs): self {
        $this->customs = $customs;

        return $this;
    }

    public function getFrozen(): ?bool {
        return $this->frozen;
    }

    public function setFrozen(?bool $frozen): self {
        $this->frozen = $frozen;

        return $this;
    }

    public function getProjectNumber(): ?string {
        return $this->projectNumber;
    }

    public function setProjectNumber(?string $projectNumber): self {
        $this->projectNumber = $projectNumber;

        return $this;
    }

    public function getBusinessUnit(): ?string {
        return $this->businessUnit;
    }

    public function setBusinessUnit(?string $businessUnit): self {
        $this->businessUnit = $businessUnit;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getDropLocation(): ?Emplacement {
        return $this->dropLocation;
    }

    public function setDropLocation(?Emplacement $dropLocation): self {
        $oldDropLocation = $this->getDropLocation();

        if($oldDropLocation
            && $oldDropLocation !== $dropLocation) {
            $oldDropLocation->removeArrival($this);
        }

        $this->dropLocation = $dropLocation;

        if($this->dropLocation
            && $oldDropLocation !== $this->dropLocation) {
            $this->dropLocation->addArrival($this);
        }

        return $this;
    }

}
