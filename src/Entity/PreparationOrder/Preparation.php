<?php

namespace App\Entity\PreparationOrder;

use App\Entity\Article;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorMessageTrait;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\PreparationOrder\PreparationRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreparationRepository::class)]
class Preparation implements PairedEntity {

    use SensorMessageTrait;

    const CATEGORIE = 'preparation';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_EN_COURS_DE_PREPARATION = 'en cours de préparation';
    const STATUT_PREPARE = 'préparé';
    const STATUT_INCOMPLETE = 'partiellement préparé';
    const STATUT_VALIDATED = 'validé';
    const STATUT_LAUNCHED = 'lancé';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $date;

    #[ORM\Column(type: 'string', length: 255, nullable: false, unique: true)]
    private $numero;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $planned = false;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $expectedAt = null;

    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'preparations')]
    private $demande;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'preparations')]
    private $statut;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'preparations')]
    private $utilisateur;

    /**
     * @var Livraison|null
     */
    #[ORM\OneToOne(targetEntity: Livraison::class, mappedBy: 'preparation')]
    private $livraison;

    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'preparationOrder')]
    private $mouvements;

    #[ORM\OneToMany(targetEntity: PreparationOrderArticleLine::class, mappedBy: 'preparation')]
    private Collection $articleLines;

    #[ORM\OneToMany(targetEntity: PreparationOrderReferenceLine::class, mappedBy: 'preparation')]
    private Collection $referenceLines;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private $endLocation;

    #[ORM\OneToMany(targetEntity: Pairing::class, mappedBy: 'preparationOrder', cascade: ['remove'])]
    private Collection $pairings;

    #[ORM\OneToMany(targetEntity: TrackingMovement::class, mappedBy: 'preparation')]
    private Collection $trackingMovements;

    public function __construct() {
        $this->mouvements = new ArrayCollection();
        $this->articleLines = new ArrayCollection();
        $this->referenceLines = new ArrayCollection();
        $this->pairings = new ArrayCollection();
        $this->sensorMessages = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(?DateTime $date): self {
        $this->date = $date;

        return $this;
    }

    public function getExpectedAt(): ?DateTime {
        return $this->expectedAt;
    }

    public function setExpectedAt(?DateTime $expectedAt): self {
        $this->expectedAt = $expectedAt;

        return $this;
    }

    public function getNumero(): ?string {
        return $this->numero;
    }

    public function setNumero(?string $numero): self {
        $this->numero = $numero;

        return $this;
    }

    /**
     * @return Demande|null
     */
    public function getDemande(): ?Demande {
        return $this->demande;
    }

    public function setDemande(?Demande $demande): self {
        $this->demande = $demande;
        return $this;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

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
     * @return Livraison|null
     */
    public function getLivraison(): ?Livraison {
        return $this->livraison;
    }

    public function setLivraison(?Livraison $livraison): self {
        if(isset($this->livraison) && ($this->livraison !== $livraison)) {
            $this->livraison->setPreparation(null);
        }

        $this->livraison = $livraison;

        if(isset($this->livraison)) {
            $this->livraison->setPreparation($this);
        }

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection {
        return $this->mouvements;
    }

    /**
     * @param Article $article
     * @return null|MouvementStock
     */
    public function getArticleMovement(Article $article): ?MouvementStock {
        $foundMovement = null;
        /** @var MouvementStock $movement */
        foreach($this->getMouvements() as $movement) {
            $movementArticle = $movement->getArticle();
            if(isset($movementArticle)
                && $movementArticle->getId() === $article->getId()) {
                $foundMovement = $movement;
                break;
            }
        }
        return $foundMovement;
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return null|MouvementStock
     */
    public function getReferenceArticleMovement(ReferenceArticle $referenceArticle): ?MouvementStock {
        $foundMovement = null;
        /** @var MouvementStock $movement */
        foreach($this->getMouvements() as $movement) {
            $movementRefArticle = $movement->getRefArticle();
            if(isset($movementRefArticle)
                && $movementRefArticle->getId() === $referenceArticle->getId()) {
                $foundMovement = $movement;
                break;
            }
        }
        return $foundMovement;
    }

    public function addMouvement(MouvementStock $mouvement): self {
        if(!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setPreparationOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getPreparationOrder() === $this) {
                $mouvement->setPreparationOrder(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?string {
        return $this->getDemande() ? $this->getDemande()->getCommentaire() : "";
    }

    public function getArticleLine(Article $article): ?PreparationOrderArticleLine {
        $correspondingArticleLines = $this->articleLines->filter(fn(PreparationOrderArticleLine $articleLine) => ($articleLine->getArticle() === $article));
        return $correspondingArticleLines->first() ?: null;
    }

    /**
     * @return Collection|PreparationOrderArticleLine[]
     */
    public function getArticleLines(): Collection {
        return $this->articleLines;
    }

    public function addArticleLine(PreparationOrderArticleLine $line): self {
        if(!$this->articleLines->contains($line)) {
            $this->articleLines[] = $line;
            $line->setPreparation($this);
        }

        return $this;
    }

    public function removeArticleLine(PreparationOrderArticleLine $line): self {
        if($this->articleLines->contains($line)) {
            $this->articleLines->removeElement($line);
            // set the owning side to null (unless already changed)
            if($line->getPreparation() === $this) {
                $line->setPreparation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|PreparationOrderReferenceLine[]
     */
    public function getReferenceLines(): Collection {
        return $this->referenceLines;
    }

    public function addReferenceLine(PreparationOrderReferenceLine $line): self {
        if(!$this->referenceLines->contains($line)) {
            $this->referenceLines[] = $line;
            $line->setPreparation($this);
        }

        return $this;
    }

    public function removeReferenceLine(PreparationOrderReferenceLine $line): self {
        if($this->referenceLines->contains($line)) {
            $this->referenceLines->removeElement($line);
            // set the owning side to null (unless already changed)
            if($line->getPreparation() === $this) {
                $line->setPreparation(null);
            }
        }

        return $this;
    }

    public function getEndLocation(): ?Emplacement {
        return $this->endLocation;
    }

    public function setEndLocation(?Emplacement $endLocation): self {
        $this->endLocation = $endLocation;
        return $this;
    }

    public function serialize() {
        $request = $this->getDemande();
        $type = $request ? $request->getType() : null;
        return [
            'numero' => $this->getNumero(),
            'statut' => $this->getStatut() ?? '',
            'date' => $this->getDate()->format('d/m/Y H:i:s') ?? '',
            'user' => $this->getUtilisateur() ? $this->getUtilisateur()->getUsername() : '',
            'type' => $type ? $type->getLabel() : '',
        ];
    }

    public function getType(): ?Type {
        return $this->getDemande()?->getType();
    }

    /**
     * @return Collection|Pairing[]
     */
    public function getPairings(): Collection {
        return $this->pairings;
    }

    public function getActivePairing(): ?Pairing {
        $criteria = Criteria::create();
        return $this->pairings
            ->matching(
                $criteria
                    ->andWhere(Criteria::expr()->eq('active', true))
                    ->setMaxResults(1)
            )
            ->first() ?: null;
    }

    public function addPairing(Pairing $pairing): self {
        if(!$this->pairings->contains($pairing)) {
            $this->pairings[] = $pairing;
            $pairing->setPreparationOrder($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self {
        if($this->pairings->removeElement($pairing)) {
            // set the owning side to null (unless already changed)
            if($pairing->getPreparationOrder() === $this) {
                $pairing->setPreparationOrder(null);
            }
        }

        return $this;
    }

    public function __toString() {
        return $this->numero;
    }

    public function isPlanned(): ?bool {
        return $this->planned;
    }

    public function setPlanned(?bool $planned): self {
        $this->planned = $planned;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if(!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setPreparation($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if($trackingMovement->getPreparation() === $this) {
                $trackingMovement->setPreparation(null);
            }
        }

        return $this;
    }

}
