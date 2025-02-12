<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Interfaces\AttachmentContainer;
use App\Entity\Statut;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\RequestTemplate\HandlingRequestTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HandlingRequestTemplateRepository::class)]
class HandlingRequestTemplate extends RequestTemplate implements AttachmentContainer {

    use AttachmentTrait;

    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'handlingRequestStatusTemplates')]
    private ?Statut $requestStatus = null;

    #[ORM\Column(type: 'string')]
    private ?string $subject = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $delay = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emergency = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $destination = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $carriedOutOperationCount = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function __construct() {
        parent::__construct();
        $this->attachments = new ArrayCollection();
    }

    public function getRequestStatus(): ?Statut {
        return $this->requestStatus;
    }

    public function setRequestStatus(?Statut $status): self {
        if($this->requestStatus && $this->requestStatus !== $status) {
            $this->requestStatus->removeHandlingRequestStatusTemplate($this);
        }
        $this->requestStatus = $status;
        if($status) {
            $status->addHandlingRequestStatusTemplate($this);
        }

        return $this;
    }

    public function getSubject(): ?string {
        return $this->subject;
    }

    public function setSubject(?string $subject): self {
        $this->subject = $subject;
        return $this;
    }

    public function getDelay(): ?int {
        return $this->delay;
    }

    public function setDelay(?int $delay): self {
        $this->delay = $delay;

        return $this;
    }

    public function getEmergency(): ?string {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self {
        $this->emergency = $emergency;

        return $this;
    }

    public function getSource(): ?string {
        return $this->source;
    }

    public function setSource(?string $source): self {
        $this->source = $source;

        return $this;
    }

    public function getDestination(): ?string {
        return $this->destination;
    }

    public function setDestination(?string $destination): self {
        $this->destination = $destination;

        return $this;
    }

    public function getCarriedOutOperationCount(): ?int {
        return $this->carriedOutOperationCount;
    }

    public function setCarriedOutOperationCount(?int $carriedOutOperationCount): self {
        $this->carriedOutOperationCount = $carriedOutOperationCount;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

}
