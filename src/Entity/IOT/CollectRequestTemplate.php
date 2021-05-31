<?php

namespace App\Entity\IOT;

use App\Entity\Emplacement;
use App\Repository\IOT\CollectRequestTemplateRepository;
use App\Entity\Type;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CollectRequestTemplateRepository::class)
 */
class CollectRequestTemplate
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="collectRequestTemplates")
     */
    private ?Type $type;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name;

    /**
     * @ORM\ManyToOne(targetEntity=TriggerAction::class, inversedBy="collectRequestTemplates")
     */
    private ?TriggerAction $triggerAction;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $subject;

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class, inversedBy="collectRequestTemplates")
     */
    private ?Emplacement $collectPoint;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $destination;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="collectRequestTypeTemplates")
     */
    private ?Type $requestType;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type !== $type) {
            $this->type->removeCollectRequestTemplate($this);
        }
        $this->type = $type;
        if($type) {
            $type->addCollectRequestTemplate($this);
        }

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setRequestType(?Type $requestType): self {
        if($this->requestType && $this->requestType !== $requestType) {
            $this->requestType->removeCollectRequestTypeTemplate($this);
        }
        $this->requestType = $requestType;
        if($requestType) {
            $requestType->addCollectRequestTypeTemplate($this);
        }

        return $this;
    }

    public function getRequestType(): ?Type {
        return $this->requestType;
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

    public function setTriggerAction(?TriggerAction $triggerAction): self {
        if($this->triggerAction && $this->triggerAction !== $triggerAction) {
            $this->triggerAction->removeCollectRequestTemplate($this);
        }
        $this->triggerAction = $triggerAction;
        if($triggerAction) {
            $triggerAction->addCollectRequestTemplate($this);
        }

        return $this;
    }

    public function getTriggerAction(): ?TriggerAction {
        return $this->triggerAction;
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

    public function setCollectPoint(?Emplacement $collectPoint): self {
        if($this->collectPoint && $this->collectPoint !== $collectPoint) {
            $this->collectPoint->removeCollectRequestTemplate($this);
        }
        $this->collectPoint = $collectPoint;
        if($collectPoint) {
            $collectPoint->addCollectRequestTemplate($this);
        }

        return $this;
    }

    public function getCollectPoint(): ?Emplacement {
        return $this->collectPoint;
    }

    public function getDestination(): ?int
    {
        return $this->destination;
    }

    public function setDestination(int $destination): self
    {
        $this->destination = $destination;

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
