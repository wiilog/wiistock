<?php

namespace App\Entity;

use App\Repository\DisputeRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DisputeRepository::class)]
class Dispute {

    // origine du litige
    const ORIGIN_RECEPTION = 'REC';
    const ORIGIN_ARRIVAGE = 'ARR';
    const DISPUTE_ARRIVAL_PREFIX = 'LA';
    const DISPUTE_RECEPTION_PREFIX = 'LR';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $creationDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $updateDate = null;

    #[ORM\ManyToMany(targetEntity: Pack::class, inversedBy: 'disputes')]
    private Collection $packs;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'disputes')]
    private ?Type $type = null;

    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'dispute')]
    private Collection $attachements;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'disputes')]
    private ?Statut $status = null;

    #[ORM\OneToMany(targetEntity: DisputeHistoryRecord::class, mappedBy: 'dispute')]
    private Collection $disputeHistory;

    #[ORM\ManyToMany(targetEntity: Article::class, inversedBy: 'disputes')]
    private Collection $articles;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'disputes')]
    private Collection $buyers;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $emergencyTriggered = null;

    #[ORM\Column(type: 'string', length: 64, nullable: false, unique: true)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reportedDisputes')]
    private ?Utilisateur $reporter = null;

    #[ORM\OneToOne(targetEntity: DisputeHistoryRecord::class)]
    private ?DisputeHistoryRecord $lastHistoryRecord = null;

    public function __construct() {
        $this->attachements = new ArrayCollection();
        $this->disputeHistory = new ArrayCollection();
        $this->packs = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->buyers = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection {
        return $this->attachements;
    }

    public function addPiecesJointe(Attachment $piecesJointe): self {
        if(!$this->attachements->contains($piecesJointe)) {
            $this->attachements[] = $piecesJointe;
            $piecesJointe->setDispute($this);
        }

        return $this;
    }

    public function removePiecesJointe(Attachment $piecesJointe): self {
        if($this->attachements->contains($piecesJointe)) {
            $this->attachements->removeElement($piecesJointe);
            // set the owning side to null (unless already changed)
            if($piecesJointe->getDispute() === $this) {
                $piecesJointe->setDispute(null);
            }
        }

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection|DisputeHistoryRecord[]
     */
    public function getDisputeHistory(): Collection {
        return $this->disputeHistory;
    }

    public function addDisputeHistoryRecord(DisputeHistoryRecord $record): self {
        if(!$this->disputeHistory->contains($record)) {
            $this->disputeHistory[] = $record;
            $record->setDispute($this);
        }

        return $this;
    }

    public function removeDisputeHistoryRecord(DisputeHistoryRecord $record): self {
        if($this->disputeHistory->contains($record)) {
            $this->disputeHistory->removeElement($record);
            // set the owning side to null (unless already changed)
            if($record->getDispute() === $this) {
                $record->setDispute(null);
            }
        }

        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getUpdateDate(): ?\DateTimeInterface {
        return $this->updateDate;
    }

    public function setUpdateDate(\DateTimeInterface $updateDate): self {
        $this->updateDate = $updateDate;

        return $this;
    }

    public function addAttachment(Attachment $attachment): self {
        if(!$this->attachements->contains($attachment)) {
            $this->attachements[] = $attachment;
            $attachment->setDispute($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self {
        if($this->attachements->contains($attachment)) {
            $this->attachements->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if($attachment->getDispute() === $this) {
                $attachment->setDispute(null);
            }
        }

        return $this;
    }

    public function getPacks() {
        return $this->packs;
    }

    public function addPack(Pack $pack): self {
        if(!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
        }

        return $this;
    }

    public function removePack(Pack $pack): self {
        if($this->packs->contains($pack)) {
            $this->packs->removeElement($pack);
        }

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection {
        return $this->articles;
    }

    /**
     * @return array|Article[]
     */
    public function getFiveLastArticles(): array {

        return array_slice($this->articles->toArray(), 0, 5);
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

    /**
     * @return Collection|Utilisateur[]
     */
    public function getBuyers(): Collection {
        return $this->buyers;
    }

    public function addBuyer(Utilisateur $buyer): self {
        if(!$this->buyers->contains($buyer)) {
            $this->buyers[] = $buyer;
        }

        return $this;
    }

    public function removeBuyer(Utilisateur $buyer): self {
        if($this->buyers->contains($buyer)) {
            $this->buyers->removeElement($buyer);
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

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(?string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getReporter(): ?Utilisateur {
        return $this->reporter;
    }

    public function setReporter(?Utilisateur $reporter): self {
        $this->reporter = $reporter;

        return $this;
    }

    public function getLastHistoryRecord(): ?DisputeHistoryRecord {
        return $this->lastHistoryRecord;
    }

    public function setLastHistoryRecord(?DisputeHistoryRecord $lastHistoryRecord): self {
        $this->lastHistoryRecord = $lastHistoryRecord;
        return $this;
    }

}
