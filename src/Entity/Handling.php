<?php

namespace App\Entity;

use App\Entity\Interfaces\StatusHistoryContainer;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\HandlingRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HandlingRepository::class)]
class Handling extends StatusHistoryContainer{

    use FreeFieldsManagerTrait;

    const CATEGORIE = 'service';
    const NUMBER_PREFIX = 'S';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $creationDate = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $subject = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $treatmentDelay = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'handlings')]
    private ?Utilisateur $requester = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'handlings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'handlings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Statut $status = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $destination = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $source = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $desiredDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $validationDate = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $emergency = null;

    #[ORM\OneToMany(mappedBy: 'handling', targetEntity: 'Attachment')]
    private Collection $attachments;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $carriedOutOperationCount = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'treatedHandlings')]
    private ?Utilisateur $treatedByHandling = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'receivedHandlings')]
    private Collection $receivers;

    #[ORM\ManyToOne(targetEntity: SensorWrapper::class)]
    private ?SensorWrapper $triggeringSensorWrapper = null;

    #[ORM\OneToMany(mappedBy: 'Handling', targetEntity: StatusHistory::class)]
    private Collection $statusHistory;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private ?bool $withoutHistory = false;

    public function __construct() {
        $this->attachments = new ArrayCollection();
        $this->emergency = false;
        $this->receivers = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
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

    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getSubject(): ?string {
        return $this->subject;
    }

    public function setSubject(string $subject): self {
        $this->subject = $subject;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getRequester(): ?utilisateur {
        return $this->requester;
    }

    public function setRequester(?utilisateur $requester): self {
        $this->requester = $requester;

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;

        return $this;
    }

    public function getDestination(): ?string {
        return $this->destination;
    }

    public function setDestination(?string $destination): self {
        $this->destination = $destination;

        return $this;
    }

    public function getSource(): ?string {
        return $this->source;
    }

    public function setSource(?string $source): self {
        $this->source = $source;

        return $this;
    }

    public function getDesiredDate(): ?DateTime {
        return $this->desiredDate;
    }

    public function setDesiredDate(?DateTime $desiredDate): self {
        $this->desiredDate = $desiredDate;

        return $this;
    }

    public function getValidationDate(): ?DateTime {
        return $this->validationDate;
    }

    public function setValidationDate(?DateTime $validationDate): self {
        $this->validationDate = $validationDate;

        return $this;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getEmergency(): ?string {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self {
        $this->emergency = $emergency;
        return $this;
    }

    /**
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection {
        return $this->attachments;
    }

    public function setAttachments($attachments): self {
        foreach($attachments as $attachment) {
            $this->addAttachment($attachment);
        }

        return $this;
    }

    public function addAttachment(Attachment $attachment): self {
        if(!$this->attachments->contains($attachment)) {
            $this->attachments[] = $attachment;
            $attachment->setHandling($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self {
        if($this->attachments->contains($attachment)) {
            $this->attachments->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if($attachment->getHandling() === $this) {
                $attachment->setHandling(null);
            }
        }

        return $this;
    }

    public function setTreatedByHandling(?utilisateur $treatedBy): self {
        $this->treatedByHandling = $treatedBy;

        return $this;
    }

    public function getTreatedByHandling(): ?Utilisateur {
        return $this->treatedByHandling;
    }

    public function setTreatmentDelay(?int $treatmentDelay): self {
        $this->treatmentDelay = $treatmentDelay;
        return $this;
    }

    public function getTreatmentDelay(): ?int {
        return $this->treatmentDelay;
    }

    public function setCarriedOutOperationCount(?int $carriedOutOperationCount): self {
        $this->carriedOutOperationCount = $carriedOutOperationCount;
        return $this;
    }

    public function getCarriedOutOperationCount(): ?int {
        return $this->carriedOutOperationCount;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getReceivers(): Collection {
        return $this->receivers;
    }

    public function addReceiver(Utilisateur $receiver): self {
        if(!$this->receivers->contains($receiver)) {
            $this->receivers[] = $receiver;
            if(!$receiver->getReceivedHandlings()->contains($this)) {
                $receiver->addReceivedHandling($this);
            }
        }

        return $this;
    }

    public function removeReceiver(Utilisateur $receiver): self {
        if($this->receivers->removeElement($receiver)) {
            $receiver->removeReceivedHandling($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, StatusHistory>
     */
    public function getStatusHistory(string $order = Criteria::ASC): Collection {
        return $this->statusHistory
            ->matching(Criteria::create()
                ->orderBy([
                    'date' => $order,
                    'id' => $order,
                ])
            );
    }

    public function addStatusHistory(StatusHistory $statusHistory): self
    {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory[] = $statusHistory;
            $statusHistory->setHandling($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self
    {
        if ($this->statusHistory->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getHandling() === $this) {
                $statusHistory->setHandling(null);
            }
        }

        return $this;
    }

    public function isWithoutHistory(): bool
    {
        return $this->withoutHistory;
    }

    public function setWithoutHistory(bool $withoutHistory): self
    {
        $this->withoutHistory = $withoutHistory;

        return $this;
    }



}
