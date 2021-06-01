<?php

namespace App\Entity\IOT;

use App\Entity\Statut;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\IOT\HandlingRequestTemplateRepository;
use App\Entity\Type;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=HandlingRequestTemplateRepository::class)
 */
class HandlingRequestTemplate extends RequestTemplate {

    use AttachmentTrait;

    /**
     * @ORM\Column(type="array")
     */
    private array $handlingFields = [];

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="handlingRequestTypeTemplates")
     */
    private ?Type $requestType = null;

    /**
     * @ORM\ManyToOne(targetEntity=Statut::class, inversedBy="handlingRequestStatusTemplates")
     */
    private ?Statut $requestStatus = null;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private ?DateTimeInterface $expected = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $emergency = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $source = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $destination = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $carriedOutOperationCount = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    public function __construct() {
        parent::__construct();
        $this->attachments = new ArrayCollection();
    }

    public function getHandlingFields(): ?array
    {
        return $this->handlingFields;
    }

    public function setHandlingFields(array $handlingFields): self
    {
        $this->handlingFields = $handlingFields;

        return $this;
    }

    public function getRequestStatus(?Statut $status): self {
        if($this->requestStatus && $this->requestStatus !== $status) {
            $this->requestStatus->removeHandlingRequestStatusTemplate($this);
        }
        $this->requestStatus = $status;
        if($status) {
            $status->addHandlingRequestStatusTemplate($this);
        }

        return $this;
    }

    public function setRequestStatus(): ?Statut {
        return $this->requestStatus;
    }

    public function getExpected(): ?DateTimeInterface
    {
        return $this->expected;
    }

    public function setExpected(?DateTimeInterface $expected): self
    {
        $this->expected = $expected;

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

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

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

    public function getCarriedOutOperationCount(): ?int
    {
        return $this->carriedOutOperationCount;
    }

    public function setCarriedOutOperationCount(?int $carriedOutOperationCount): self
    {
        $this->carriedOutOperationCount = $carriedOutOperationCount;

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
}
