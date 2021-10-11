<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LitigeRepository")
 */
class Litige
{
    // origine du litige
    const ORIGIN_RECEPTION = 'REC';
    const ORIGIN_ARRIVAGE = 'ARR';

    const DISPUTE_ARRIVAL_PREFIX = 'LA';
    const DISPUTE_RECEPTION_PREFIX = 'LR';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;


	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $creationDate;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $updateDate;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Pack", inversedBy="litiges")
     */
    private $packs;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="litiges")
     */
    private $type;

    /**
     * @ORM\OneToMany(targetEntity="Attachment", mappedBy="litige")
     */
    private $attachements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="litiges")
     */
    private $status;

    /**
     * @ORM\OneToMany(targetEntity="DisputeHistoryRecord", mappedBy="dispute")
     */
    private Collection $disputeHistory;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Article", inversedBy="litiges")
     */
    private $articles;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Utilisateur", inversedBy="litiges")
     */
    private $buyers;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $emergencyTriggered;

    /**
     * @ORM\Column(type="string", length=64, nullable=false, unique=true)
     */
    private $numeroLitige;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="litigesDeclarant")
     */
    private $declarant;

    public function __construct()
    {
        $this->attachements = new ArrayCollection();
        $this->disputeHistory = new ArrayCollection();
        $this->packs = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->buyers = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
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
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection
    {
        return $this->attachements;
    }

    public function addPiecesJointe(Attachment $piecesJointe): self
    {
        if (!$this->attachements->contains($piecesJointe)) {
            $this->attachements[] = $piecesJointe;
            $piecesJointe->setLitige($this);
        }

        return $this;
    }

    public function removePiecesJointe(Attachment $piecesJointe): self
    {
        if ($this->attachements->contains($piecesJointe)) {
            $this->attachements->removeElement($piecesJointe);
            // set the owning side to null (unless already changed)
            if ($piecesJointe->getLitige() === $this) {
                $piecesJointe->setLitige(null);
            }
        }

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection|DisputeHistoryRecord[]
     */
    public function getDisputeHistory(): Collection
    {
        return $this->disputeHistory;
    }

    public function addDisputeHistoryRecord(DisputeHistoryRecord $record): self
    {
        if (!$this->disputeHistory->contains($record)) {
            $this->disputeHistory[] = $record;
            $record->setDispute($this);
        }

        return $this;
    }

    public function removeDisputeHistoryRecord(DisputeHistoryRecord $record): self
    {
        if ($this->disputeHistory->contains($record)) {
            $this->disputeHistory->removeElement($record);
            // set the owning side to null (unless already changed)
            if ($record->getDispute() === $this) {
                $record->setDispute(null);
            }
        }

        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getUpdateDate(): ?\DateTimeInterface
    {
        return $this->updateDate;
    }

    public function setUpdateDate(\DateTimeInterface $updateDate): self
    {
        $this->updateDate = $updateDate;

        return $this;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachements->contains($attachment)) {
            $this->attachements[] = $attachment;
            $attachment->setLitige($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        if ($this->attachements->contains($attachment)) {
            $this->attachements->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if ($attachment->getLitige() === $this) {
                $attachment->setLitige(null);
            }
        }

        return $this;
    }

    public function getPacks()
    {
        return $this->packs;
    }

    public function addPack(Pack $pack): self
    {
        if (!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
        }

        return $this;
    }

    public function removePack(Pack $pack): self
    {
        if ($this->packs->contains($pack)) {
            $this->packs->removeElement($pack);
        }

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    /**
     * @return array|Article[]
     */
    public function getFiveLastArticles(): array
    {

        return array_slice($this->articles->toArray(), 0, 5);
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

    /**
     * @return Collection|Utilisateur[]
     */
    public function getBuyers(): Collection
    {
        return $this->buyers;
    }

    public function addBuyer(Utilisateur $buyer): self
    {
        if (!$this->buyers->contains($buyer)) {
            $this->buyers[] = $buyer;
        }

        return $this;
    }

    public function removeBuyer(Utilisateur $buyer): self
    {
        if ($this->buyers->contains($buyer)) {
            $this->buyers->removeElement($buyer);
        }

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

    public function getNumeroLitige(): ?string
    {
        return $this->numeroLitige;
    }

    public function setNumeroLitige(?string $numeroLitige): self
    {
        $this->numeroLitige = $numeroLitige;

        return $this;
    }

    public function getDeclarant(): ?Utilisateur
    {
        return $this->declarant;
    }

    public function setDeclarant(?Utilisateur $declarant): self
    {
        $this->declarant = $declarant;

        return $this;
    }

    public function serialize()
    {
        return [
            'numeroLitige' => $this->getNumeroLitige(),
            'type' => $this->getType() ? $this->getType()->getLabel() : '',
            'status' => $this->getStatus() ? $this->getStatus()->getNom() : '',
            'creationDate' => $this->getCreationDate() ? $this->getCreationDate()->format('d/m/Y') : '',
            'updateDate' => $this->getUpdateDate() ? $this->getUpdateDate()->format('d/m/Y') : '',
        ];
    }

}
