<?php

namespace App\Entity;

use App\Entity\Emergency\StockEmergency;
use App\Entity\Tracking\TrackingMovement;
use App\Repository\ReceptionReferenceArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReceptionReferenceArticleRepository::class)]
class ReceptionReferenceArticle {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ReceptionLine::class, inversedBy: 'receptionReferenceArticles')]
    private ?ReceptionLine $receptionLine = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'receptionReferenceArticles')]
    private ?ReferenceArticle $referenceArticle = null;

    /** Received quantity */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantite = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    /** Quantity to receive */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantiteAR = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'receptionReferenceArticles')]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $anomalie = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $commande = null;

    #[ORM\ManyToOne(targetEntity: ArticleFournisseur::class, inversedBy: 'receptionReferenceArticles')]
    private ?ArticleFournisseur $articleFournisseur = null;

    #[ORM\OneToMany(mappedBy: 'receptionReferenceArticle', targetEntity: Article::class)]
    private Collection $articles;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $emergencyTriggered = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $emergencyComment = null;

    #[ORM\OneToMany(mappedBy: 'receptionReferenceArticle', targetEntity: TrackingMovement::class)]
    private Collection $trackingMovements;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\ManyToOne(inversedBy: 'receptionReferenceArticle')]
    private ?StockEmergency $stockEmergency = null;

    public function __construct() {
        $this->articles = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getReceptionLine(): ?ReceptionLine {
        return $this->receptionLine;
    }

    public function setReceptionLine(?ReceptionLine $receptionLine): self {
        if($this->receptionLine && $this->receptionLine !== $receptionLine) {
            $this->receptionLine->removeReceptionReferenceArticle($this);
        }
        $this->receptionLine = $receptionLine;
        $receptionLine?->addReceptionReferenceArticle($this);
        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self {
        $this->referenceArticle = $referenceArticle;

        return $this;
    }

    public function getQuantite(): ?int {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self {
        $this->quantite = $quantite;

        return $this;
    }

    public function getCommentaire(): ?string {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getQuantiteAR(): ?int {
        return $this->quantiteAR;
    }

    public function setQuantiteAR(?int $quantiteAR): self {
        $this->quantiteAR = $quantiteAR;

        return $this;
    }

    public function getFournisseur(): ?Fournisseur {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): self {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getAnomalie(): ?bool {
        return $this->anomalie;
    }

    public function setAnomalie(?bool $anomalie): self {
        $this->anomalie = $anomalie;

        return $this;
    }

    public function getCommande(): ?string {
        return $this->commande;
    }

    public function setCommande(?string $commande): self {
        $this->commande = $commande;

        return $this;
    }

    public function getArticleFournisseur(): ?ArticleFournisseur {
        return $this->articleFournisseur;
    }

    public function setArticleFournisseur(?ArticleFournisseur $articleFournisseur): self {
        $this->articleFournisseur = $articleFournisseur;

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
            $article->setReceptionReferenceArticle($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        if($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if($article->getReceptionReferenceArticle() === $this) {
                $article->setReceptionReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getEmergencyTriggered(): ?bool {
        return $this->emergencyTriggered;
    }

    public function setEmergencyTriggered(?bool $emergencyTriggered): self {
        $this->emergencyTriggered = $emergencyTriggered;

        return $this;
    }

    public function getEmergencyComment(): ?string {
        return $this->emergencyComment;
    }

    public function setEmergencyComment(?string $emergencyComment): self {
        $this->emergencyComment = $emergencyComment;

        return $this;
    }

    /**
     * @return Collection|TrackingMovement[]
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if(!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setReceptionReferenceArticle($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if($trackingMovement->getReceptionReferenceArticle() === $this) {
                $trackingMovement->setReceptionReferenceArticle(null);
            }
        }

        return $this;
    }

    public function isReceptionBegun(): bool {
        return (
            !$this->articles->isEmpty()
            || ($this->quantite && $this->quantite > 0)
        );
    }

    public function getUnitPrice(): ?float {
        return isset($this->unitPrice)
            ? ((float) $this->unitPrice)
            : null;
    }

    public function setUnitPrice(?float $unitPrice): self {
        $this->unitPrice = isset($unitPrice)
            ? ((string) $unitPrice)
            : null;

        return $this;
    }

    public function getStockEmergency(): ?StockEmergency
    {
        return $this->stockEmergency;
    }

    public function setStockEmergency(?StockEmergency $stockEmergency): self
    {
        $this->stockEmergency = $stockEmergency;

        return $this;
    }

}
