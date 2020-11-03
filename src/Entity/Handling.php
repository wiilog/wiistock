<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HandlingRepository")
 */
class Handling extends FreeFieldEntity
{
    const CATEGORIE = 'service';

    const PREFIX_NUMBER = 'S';

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
     * @ORM\Column(type="string", length=64)
     */
    private $subject;

    /**
     * @ORM\Column(type="integer", nullable=true, options={"unsigned": true})
     */
    private $treatmentDelay;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="handlings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $requester;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="handlings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="handlings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $destination;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $source;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
    private $desiredDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validationDate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $number;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $emergency;

    /**
     * @ORM\OneToMany(targetEntity="Attachment", mappedBy="handling")
     */
    private $attachments;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $carriedOutOperationCount;

    /**
     * @var Utilisateur|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="treatedHandlings")
     */
    private $treatedByHandling;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        $this->emergency = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreationDate(): ?DateTime
    {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getRequester(): ?utilisateur
    {
        return $this->requester;
    }

    public function setRequester(?utilisateur $requester): self
    {
        $this->requester = $requester;

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

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getDesiredDate(): ?DateTime
    {
        return $this->desiredDate;
    }

    public function setDesiredDate(?DateTime $desiredDate): self
    {
        $this->desiredDate = $desiredDate;

        return $this;
    }

    public function getValidationDate(): ?DateTime
    {
        return $this->validationDate;
    }

    public function setValidationDate(?DateTime $validationDate): self
    {
        $this->validationDate = $validationDate;

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

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEmergency(): ?string
    {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self
    {
        $this->emergency = $emergency;
        return $this;
    }

    /**
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments[] = $attachment;
            $attachment->setHandling($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        if ($this->attachments->contains($attachment)) {
            $this->attachments->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if ($attachment->getHandling() === $this) {
                $attachment->setHandling(null);
            }
        }

        return $this;
    }

    public function setTreatedByHandling(?utilisateur $treatedBy): self
    {
        $this->treatedByHandling = $treatedBy;

        return $this;
    }

    public function getTreatedByHandling(): ?Utilisateur
    {
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
}
