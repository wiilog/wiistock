<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DispatchRepository")
 */
class Dispatch extends FreeFieldEntity
{
    const CATEGORIE = 'acheminements';

    const PREFIX_NUMBER = 'A-';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="receivedDispatches")
     * @ORM\JoinColumn(nullable=true)
     */
    private $receiver;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="requestedDispatches")
     * @ORM\JoinColumn(nullable=false)
     */
    private $requester;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @var bool
     * @ORM\Column(type="boolean", nullable=false, options={"default": false})
     */
    private $urgent;

    /**
     * @var DateTime|null
     * @ORM\Column(type="date", nullable=true)
     */
    private $startDate;

    /**
     * @var DateTime|null
     * @ORM\Column(type="date", nullable=true)
     */
    private $endDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="dispatches")
     * @ORM\JoinColumn(nullable=false)
     */
    private $statut;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PieceJointe", mappedBy="dispatch")
     */
    private $attachements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="dispatchesFrom")
     */
    private $locationFrom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="dispatchesTo")
     */
    private $locationTo;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DispatchPack", mappedBy="dispatch", orphanRemoval=true)
     */
    private $dispatchPacks;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementTraca", mappedBy="dispatch")
     */
    private $trackingMovements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="dispatches")
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $number;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validationDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $treatmentDate;

    public function __construct()
    {
        $this->dispatchPacks = new ArrayCollection();
        $this->attachements = new ArrayCollection();
        $this->urgent = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $date): self
    {
        $this->creationDate = $date;

        return $this;
    }

    public function getReceiver(): ?Utilisateur
    {
        return $this->receiver;
    }

    public function setReceiver(?Utilisateur $receiver): self
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getRequester(): ?Utilisateur
    {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self
    {
        $this->requester = $requester;

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
    public function getAttachments(): Collection
    {
        return $this->attachements;
    }

    public function addAttachment(PieceJointe $attachment): self
    {
        if (!$this->attachements->contains($attachment)) {
            $this->attachements[] = $attachment;
            $attachment->setDispatch($this);
        }

        return $this;
    }

    public function removeAttachment(PieceJointe $attachment): self
    {
        if ($this->attachements->contains($attachment)) {
            $this->attachements->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if ($attachment->getDispatch() === $this) {
                $attachment->setDispatch(null);
            }
        }

        return $this;
    }

    public function getLocationFrom(): ?Emplacement
    {
        return $this->locationFrom;
    }

    public function setLocationFrom(?Emplacement $locationFrom): self
    {
        $this->locationFrom = $locationFrom;

        return $this;
    }

    public function getLocationTo(): ?Emplacement
    {
        return $this->locationTo;
    }

    public function setLocationTo(?Emplacement $locationTo): self
    {
        $this->locationTo = $locationTo;

        return $this;
    }

    /**
     * @return Collection|DispatchPack[]
     */
    public function getDispatchPacks(): Collection
    {
        return $this->dispatchPacks;
    }

    public function addDispatchPack(DispatchPack $dispatchPack): self
    {
        if (!$this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks[] = $dispatchPack;
            $dispatchPack->setDispatch($this);
        }

        return $this;
    }

    public function removeDispatchPack(DispatchPack $dispatchPack): self
    {
        if ($this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks->removeElement($dispatchPack);
            // set the owning side to null (unless already changed)
            if ($dispatchPack->getDispatch() === $this) {
                $dispatchPack->setDispatch(null);
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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function isUrgent(): bool {
        return $this->urgent;
    }

    public function setUrgent(bool $urgent): self {
        $this->urgent = $urgent;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getStartDate(): ?DateTime {
        return $this->startDate;
    }

    /**
     * @param DateTime|null $startDate
     * @return self
     */
    public function setStartDate(?DateTime $startDate): self {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getEndDate(): ?DateTime {
        return $this->endDate;
    }

    /**
     * @param DateTime|null $endDate
     * @return self
     */
    public function setEndDate(?DateTime $endDate): self {
        $this->endDate = $endDate;
        return $this;
    }

    public function getValidationDate(): ?\DateTimeInterface {
        return $this->validationDate;
    }

    public function setValidationDate(?\DateTimeInterface $validationDate): self {
        $this->validationDate = $validationDate;
        return $this;
    }

    public function getTreatmentDate(): ?\DateTimeInterface {
        return $this->treatmentDate;
    }

    public function setTreatmentDate(?\DateTimeInterface $treatmentDate): self {
        $this->treatmentDate = $treatmentDate;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getTrackingMovements(): Collection
    {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(MouvementTraca $trackingMovement): self
    {
        if (!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setDispatch($this);
        }

        return $this;
    }

    public function removeTrackingMovement(MouvementTraca $trackingMovement): self
    {
        if ($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if ($trackingMovement->getDispatch() === $this) {
                $trackingMovement->setDispatch(null);
            }
        }

        return $this;
    }

}
