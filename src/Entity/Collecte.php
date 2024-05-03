<?php

namespace App\Entity;

use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorMessageTrait;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Traits\CleanedCommentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\CollecteRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: CollecteRepository::class)]
class Collecte implements PairedEntity {

    const CATEGORIE = 'collecte';
    const STATUT_COLLECTE = 'collecté';
    const STATUT_INCOMPLETE = 'partiellement collecté';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_BROUILLON = 'brouillon';
    const DESTRUCT_STATE = 0;
    const STOCKPILLING_STATE = 1;

    const NUMBER_PREFIX = 'C';

    use CleanedCommentTrait;
    use SensorMessageTrait;
    use FreeFieldsManagerTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private $numero;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $date;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $validationDate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $objet;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'collectes')]
    private $pointCollecte;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'collectes')]
    private $demandeur;

    #[ORM\ManyToMany(targetEntity: Article::class, inversedBy: 'collectes')]
    private $articles;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'collectes')]
    private $statut;

    #[ORM\Column(type: 'text', nullable: true)]
    private $commentaire;

    #[ORM\OneToMany(targetEntity: CollecteReference::class, mappedBy: 'collecte', cascade: ['persist', 'remove'])]
    private $collecteReferences;

    #[ORM\Column(type: 'boolean')]
    private $stockOrDestruct;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'collectes')]
    private $type;

    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'collecteOrder')]
    private $mouvements;

    #[ORM\OneToMany(targetEntity: OrdreCollecte::class, mappedBy: 'demandeCollecte')]
    private $ordreCollecte;

    #[ORM\ManyToOne(targetEntity: SensorWrapper::class)]
    private ?SensorWrapper $triggeringSensorWrapper = null;

    #[ORM\ManyToOne(targetEntity: Kiosk::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Kiosk $kiosk = null;

    public function __construct() {
        $this->articles = new ArrayCollection();
        $this->collecteReferences = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->ordreCollecte = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getTriggeringSensorWrapper(): ?SensorWrapper {
        return $this->triggeringSensorWrapper;
    }

    public function setTriggeringSensorWrapper(?SensorWrapper $triggeringSensorWrapper): self {
        $this->triggeringSensorWrapper = $triggeringSensorWrapper;
        return $this;
    }

    public function getNumero(): ?string {
        return $this->numero;
    }

    public function setNumero(?string $numero): self {
        $this->numero = $numero;

        return $this;
    }

    public function getDate(): ?DateTimeInterface {
        return $this->date;
    }

    public function setDate(?DateTimeInterface $date): self {
        $this->date = $date;

        return $this;
    }

    public function getDemandeur(): ?Utilisateur {
        return $this->demandeur;
    }

    public function setDemandeur(?Utilisateur $demandeur): self {
        $this->demandeur = $demandeur;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection {
        return $this->articles;
    }

    public function addArticle(Article $article): self {
        if(!$this->articles->contains($article)) {
            $this->articles[] = $article;
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        if($this->articles->contains($article)) {
            $this->articles->removeElement($article);
        }

        return $this;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

        return $this;
    }

    public function getObjet(): ?string {
        return $this->objet;
    }

    public function setObjet(?string $objet): self {
        $this->objet = $objet;

        return $this;
    }

    public function getPointCollecte(): ?Emplacement {
        return $this->pointCollecte;
    }

    public function setPointCollecte(?Emplacement $pointCollecte): self {
        $this->pointCollecte = $pointCollecte;

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
     * @return Collection|CollecteReference[]
     */
    public function getCollecteReferences(): Collection {
        return $this->collecteReferences;
    }

    public function addCollecteReference(CollecteReference $collecteReference): self {
        if(!$this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences[] = $collecteReference;
            $collecteReference->setCollecte($this);
        }

        return $this;
    }

    public function removeCollecteReference(CollecteReference $collecteReference): self {
        if($this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences->removeElement($collecteReference);
            // set the owning side to null (unless already changed)
            if($collecteReference->getCollecte() === $this) {
                $collecteReference->setCollecte(null);
            }
        }

        return $this;
    }

    public function isStock(): ?bool {
        return $this->stockOrDestruct == true;
    }

    public function isDestruct(): ?bool {
        return $this->stockOrDestruct == false;
    }

    public function setStockOrDestruct(bool $stockOrDestruct): self {
        $this->stockOrDestruct = $stockOrDestruct;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection|OrdreCollecte[]
     */
    public function getOrdresCollecte(): Collection {
        return $this->ordreCollecte;
    }

    public function getValidationDate(): ?DateTimeInterface {
        return $this->validationDate;
    }

    public function setValidationDate(?DateTimeInterface $validationDate): self {
        $this->validationDate = $validationDate;

        return $this;
    }

    public function addOrdreCollecte(OrdreCollecte $ordreCollecte): self {
        if(!$this->ordreCollecte->contains($ordreCollecte)) {
            $this->ordreCollecte[] = $ordreCollecte;
            $ordreCollecte->setDemandeCollecte($this);
        }

        return $this;
    }

    public function removeOrdreCollecte(OrdreCollecte $ordreCollecte): self {
        if($this->ordreCollecte->contains($ordreCollecte)) {
            $this->ordreCollecte->removeElement($ordreCollecte);
            // set the owning side to null (unless already changed)
            if($ordreCollecte->getDemandeCollecte() === $this) {
                $ordreCollecte->setDemandeCollecte(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self {
        if(!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setCollecteOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getCollecteOrder() === $this) {
                $mouvement->setCollecteOrder(null);
            }
        }

        return $this;
    }

    public function getPairings(): Collection {
        $pairingsArray = Stream::from($this->getOrdresCollecte()->toArray())
            ->flatMap(fn(OrdreCollecte $collectOrder) => $collectOrder->getPairings()->toArray())
            ->toArray();
        return new ArrayCollection($pairingsArray);
    }

    public function getActivePairing(): ?Pairing {
        $activePairing = null;
        foreach($this->getOrdresCollecte() as $collectOrder) {
            $activePairing = $collectOrder->getActivePairing();
            if(isset($activePairing)) {
                break;
            }
        }
        return $activePairing;
    }

    public function getKiosk(): ?Kiosk {
        return $this->kiosk;
    }

    public function setKiosk(?Kiosk $kiosk): self {
        $this->kiosk = $kiosk;

        return $this;
    }

}
