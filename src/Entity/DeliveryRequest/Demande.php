<?php

namespace App\Entity\DeliveryRequest;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorMessageTrait;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Livraison;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Reception;
use App\Entity\Statut;
use App\Entity\Traits\CommentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Entity\Traits\RequestTrait;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DeliveryRequest\DemandeRepository")
 */
class Demande implements PairedEntity {

    const CATEGORIE = 'demande';

    const STATUT_BROUILLON = 'brouillon';
    const STATUT_PREPARE = 'préparé';
    const STATUT_INCOMPLETE = 'partiellement préparé';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_LIVRE = 'livré';
    const STATUT_LIVRE_INCOMPLETE = 'livré partiellement';

    use CommentTrait;
    use RequestTrait;
    use SensorMessageTrait;
    use FreeFieldsManagerTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     */
    private ?string $numero = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="demandes")
     */
    private ?Emplacement $destination = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="demandes")
     */
    private ?Utilisateur $utilisateur = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $createdAt = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PreparationOrder\Preparation", mappedBy="demande")
     */
    private Collection $preparations;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="demandes")
     */
    private ?Statut $statut = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="demandesLivraison")
     */
    private ?Type $type = null;

    /**
     * @ORM\OneToMany(targetEntity=DeliveryRequestReferenceLine::class, mappedBy="request")
     */
    private Collection $referenceLines;

    /**
     * @ORM\OneToMany(targetEntity=DeliveryRequestArticleLine::class, mappedBy="request")
     */
    private Collection $articleLines;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $commentaire = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="demandes")
     */
    private ?Reception $reception = null;

    /**
     * @ORM\ManyToOne(targetEntity=SensorWrapper::class)
     */
    private ?SensorWrapper $triggeringSensorWrapper = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $validatedAt = null;

    public function __construct() {
        $this->preparations = new ArrayCollection();
        $this->referenceLines = new ArrayCollection();
        $this->articleLines = new ArrayCollection();
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

    public function getDestination(): ?Emplacement {
        return $this->destination;
    }

    public function setDestination(?Emplacement $destination): self {
        $this->destination = $destination;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations(): Collection {
        return $this->preparations;
    }

    public function addPreparation(?Preparation $preparation): self {
        if(!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setDemande($this);
        }

        return $this;
    }

    public function removePreparation(?Preparation $preparation): self {
        if(!$this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if($preparation->getDemande() === $this) {
                $preparation->setDemande(null);
            }
        }

        return $this;
    }

    /**
     * @return Livraison[]|Collection
     */
    public function getLivraisons(): Collection {
        return $this->getPreparations()->map(function(Preparation $preparation) {
            return $preparation->getLivraison();
        })->filter(function(?Livraison $livraison) {
            return isset($livraison);
        });
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

        return $this;
    }

    public function getArticleLine(Article $article): DeliveryRequestArticleLine {
        $articleLines = Stream::from($this->articleLines->toArray());
        return $articleLines
            ->filter(fn(DeliveryRequestArticleLine $line) => $line->getArticle() === $article)
            ->first();
    }

    /**
     * @return Collection|DeliveryRequestReferenceLine[]
     */
    public function getReferenceLines(): Collection {
        return $this->referenceLines;
    }

    public function addReferenceLine(DeliveryRequestReferenceLine $line): self {
        if(!$this->referenceLines->contains($line)) {
            $this->referenceLines[] = $line;
            $line->setRequest($this);
        }

        return $this;
    }

    public function removeReferenceLine(DeliveryRequestReferenceLine $line): self {
        if($this->referenceLines->contains($line)) {
            $this->referenceLines->removeElement($line);
            if($line->getRequest() === $this) {
                $line->setRequest(null);
            }
        }

        return $this;
    }

    public function setReferenceLines(?array $lines): self {
        foreach($this->getReferenceLines()->toArray() as $line) {
            $this->removeReferenceLine($line);
        }

        $this->referenceLines = new ArrayCollection();
        foreach($lines as $line) {
            $this->addReferenceLine($line);
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
     * @return Collection|DeliveryRequestArticleLine[]
     */
    public function getArticleLines(): Collection {
        return $this->articleLines;
    }

    public function addArticleLine(DeliveryRequestArticleLine $line): self {
        if(!$this->articleLines->contains($line)) {
            $this->articleLines[] = $line;
            $line->setRequest($this);
        }

        return $this;
    }

    public function removeArticleLine(DeliveryRequestArticleLine $line): self {
        if($this->articleLines->contains($line)) {
            $this->articleLines->removeElement($line);
            if($line->getRequest() === $this) {
                $line->setRequest(null);
            }
        }

        return $this;
    }

    public function setArticleLines(?array $lines): self {
        foreach($this->getArticleLines()->toArray() as $line) {
            $this->removeArticleLine($line);
        }

        $this->articleLines = new ArrayCollection();
        foreach($lines as $line) {
            $this->addArticleLine($line);
        }

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getReception(): ?Reception {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self {
        $this->reception = $reception;

        return $this;
    }

    public function needsToBeProcessed(): bool {
        $demandeStatus = $this->getStatut();
        return (
            $demandeStatus
            && (
                $demandeStatus->getNom() === Demande::STATUT_A_TRAITER
                || $demandeStatus->getNom() === Demande::STATUT_PREPARE
            )
        );
    }

    public function getPairings(): Collection {
        $pairingsArray = Stream::from($this->getPreparations()->toArray())
            ->flatMap(fn(Preparation $preparation) => $preparation->getPairings()->toArray())
            ->toArray();
        return new ArrayCollection($pairingsArray);
    }

    public function getActivePairing(): ?Pairing {
        $activePairing = null;
        foreach($this->getPreparations() as $preparation) {
            $activePairing = $preparation->getActivePairing();
            if(isset($activePairing)) {
                break;
            }
        }
        return $activePairing;
    }

    public function getValidatedAt(): ?DateTime {
        return $this->validatedAt;
    }

    public function setValidatedAt(?DateTime $validatedAt): self {
        $this->validatedAt = $validatedAt;

        return $this;
    }

}
