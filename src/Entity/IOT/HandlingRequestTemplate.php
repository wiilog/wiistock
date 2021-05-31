<?php

namespace App\Entity\IOT;

use App\Entity\FreeFieldEntity;
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
class HandlingRequestTemplate extends FreeFieldEntity
{

    use AttachmentTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="handlingRequestTemplates")
     */
    private ?Type $type;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name;

    /**
     * @ORM\Column(type="array")
     */
    private array $handlingFields = [];

    /**
     * @ORM\ManyToOne(targetEntity=TriggerAction::class, inversedBy="handlingRequestTemplates")
     */
    private ?TriggerAction $triggerAction;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="handlingRequestTypeTemplates")
     */
    private ?Type $requestType;

    /**
     * @ORM\ManyToOne(targetEntity=Statut::class, inversedBy="handlingRequestStatusTemplates")
     */
    private ?Statut $requestStatus;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private ?DateTimeInterface $expected;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $emergency;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $source;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $destination;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $carriedOutOperationCount;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment;

    public function __construct() {
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type !== $type) {
            $this->type->removeHandlingRequestTemplate($this);
        }
        $this->type = $type;
        if($type) {
            $type->addHandlingRequestTemplate($this);
        }

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
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

    public function setTriggerAction(?TriggerAction $triggerAction): self {
        if($this->triggerAction && $this->triggerAction !== $triggerAction) {
            $this->triggerAction->removeHandlingRequestTemplate($this);
        }
        $this->triggerAction = $triggerAction;
        if($triggerAction) {
            $triggerAction->addHandlingRequestTemplate($this);
        }

        return $this;
    }

    public function getTriggerAction(): ?TriggerAction {
        return $this->triggerAction;
    }

    public function setRequestType(?Type $requestType): self {
        if($this->requestType && $this->requestType !== $requestType) {
            $this->requestType->removeHandlingRequestTypeTemplate($this);
        }
        $this->requestType = $requestType;
        if($requestType) {
            $requestType->addHandlingRequestTypeTemplate($this);
        }

        return $this;
    }

    public function getRequestType(): ?Type {
        return $this->requestType;
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
